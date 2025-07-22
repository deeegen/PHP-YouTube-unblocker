<?php
// index.php
declare(strict_types=1);
header("Content-Type: text/html; charset=UTF-8");

function curlGet(string $url, int $timeout = 10): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            'Referer:',
            'Cookie:',
            'User-Agent: Mozilla/5.0 (compatible; MyYouTubeProxy/1.0)'
        ],
    ]);
    $data = curl_exec($ch);
    if ($data === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL request failed: {$err}");
    }
    curl_close($ch);
    return $data;
}

function fetchYoutubeResults(string $query): array
{
    $html = curlGet('https://www.youtube.com/results?search_query=' . urlencode($query));
    if (!preg_match('/var ytInitialData = (.*?);<\/script>/', $html, $m)) {
        throw new RuntimeException('Failed to extract initial data.');
    }
    $json   = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    $blocks = $json['contents']['twoColumnSearchResultsRenderer']
                   ['primaryContents']['sectionListRenderer']['contents'] ?? [];
    $out = [];
    foreach ($blocks as $sec) {
        if (empty($sec['itemSectionRenderer'])) {
            continue;
        }
        foreach ($sec['itemSectionRenderer']['contents'] as $item) {
            if (empty($item['videoRenderer'])) {
                continue;
            }
            $vr   = $item['videoRenderer'];
            $id   = $vr['videoId'] ?? '';
            $title= $vr['title']['runs'][0]['text'] ?? '';
            $thumbs = $vr['thumbnail']['thumbnails'] ?? [];
            $thumb  = end($thumbs)['url'] ?? '';
            if ($id) {
                $out[] = ['id'=>$id,'title'=>$title,'thumbnail'=>$thumb];
            }
        }
    }
    return $out;
}

$q = $_GET['q'] ?? null;
$v = $_GET['v'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>YouTube Unblocker</title>
  <style>
    /* Reset and base */
    * {
      box-sizing: border-box;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen,
        Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
      margin: 20px;
      background: #fdfdfd;
      color: #222;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0 10px;
    }
    h1 {
      color: #cc0000;
      margin-bottom: 20px;
      user-select: none;
    }

    /* Search bar */
    form.search-form {
      width: 100%;
      max-width: 600px;
      display: flex;
      margin-bottom: 30px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      border-radius: 30px;
      overflow: hidden;
      background: white;
      border: 1px solid #ddd;
      transition: border-color 0.3s ease;
    }
    form.search-form:focus-within {
      border-color: #cc0000;
      box-shadow: 0 0 10px #cc0000aa;
    }
    form.search-form input[type="text"] {
      flex: 1;
      border: none;
      padding: 14px 20px;
      font-size: 1rem;
      outline: none;
      border-radius: 30px 0 0 30px;
    }
    form.search-form button {
      background: #cc0000;
      border: none;
      color: white;
      padding: 0 25px;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s ease;
      border-radius: 0 30px 30px 0;
    }
    form.search-form button:hover,
    form.search-form button:focus {
      background: #a00000;
      outline: none;
    }

    /* Video player */
    video {
      width: 100%;
      max-width: 720px;
      max-height: 420px;
      background: #222;
      border-radius: 8px;
      box-shadow: 0 0 12px rgba(0,0,0,0.3);
      margin-bottom: 20px;
      outline: none;
    }

    /* Video list results */
    .video {
      display: flex;
      align-items: center;
      background: white;
      padding: 10px 12px;
      margin: 8px 0;
      max-width: 720px;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
      transition: box-shadow 0.2s ease;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
    }
    .video:hover {
      box-shadow: 0 3px 12px rgba(0,0,0,0.15);
      background: #fff6f6;
    }
    .video img {
      width: 160px;
      height: 90px;
      flex-shrink: 0;
      border-radius: 4px;
      object-fit: cover;
      margin-right: 16px;
      user-select: none;
    }
    .video a {
      font-weight: 600;
      font-size: 1rem;
      color: #cc0000;
      text-decoration: none;
      line-height: 1.3;
      flex: 1;
    }
    .video a:hover,
    .video a:focus {
      text-decoration: underline;
      outline: none;
    }

    /* Responsive tweaks */
    @media (max-width: 640px) {
      form.search-form {
        max-width: 100%;
      }
      .video {
        max-width: 100%;
      }
      .video img {
        width: 120px;
        height: 68px;
        margin-right: 12px;
      }
    }
  </style>
</head>
<body>
  <h1>YouTube Unblocker</h1>

  <form method="GET" class="search-form" role="search" aria-label="YouTube Search Form">
    <input
      type="text"
      name="q"
      placeholder="Search YouTube…"
      value="<?=htmlspecialchars($q, ENT_QUOTES)?>"
      autocomplete="off"
      required
      autofocus
      aria-required="true"
    />
    <button type="submit" aria-label="Search">
      🔍
    </button>
  </form>

  <?php if ($v): ?>
    <h2>Watching Video</h2>
    <div id="player">
      <p>Loading video…</p>
    </div>
    <script>
      fetch('proxy.php?video=<?=rawurlencode($v)?>')
      .then(r => r.json())
      .then(data => {
        if(data.url){
          document.getElementById('player').innerHTML =
            '<video src="'+data.url.replace(/"/g, '&quot;')+'" controls autoplay></video>';
        } else {
          document.getElementById('player').innerHTML =
            '<p style="color:red;">Failed to fetch video URL.</p>';
        }
      })
      .catch(e => {
        document.getElementById('player').innerHTML =
          '<p style="color:red;">Failed (' + e + ').</p>';
      });
    </script>
  <?php endif; ?>

  <?php if ($q): ?>
    <?php
      try {
        $res = fetchYoutubeResults($q);
      } catch (Throwable $e) {
        echo '<p style="color:red;">Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        $res = [];
      }
    ?>
    <?php if (empty($res)): ?>
      <p>No results.</p>
    <?php else: ?>
      <?php foreach ($res as $vid): ?>
        <a href="?q=<?=urlencode($q)?>&v=<?=htmlspecialchars($vid['id'], ENT_QUOTES)?>" class="video">
          <img src="<?=htmlspecialchars($vid['thumbnail'], ENT_QUOTES)?>" alt="" loading="lazy" />
          <span><?=htmlspecialchars($vid['title'], ENT_QUOTES)?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

</body>
</html>
