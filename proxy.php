<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

if (empty($_GET['video']) || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $_GET['video'])) {
    http_response_code(400);
    exit('Invalid video ID.');
}

$vid = $_GET['video'];
$video_url = "https://www.youtube.com/watch?v=$vid";

// Absolute path to yt-dlp binary
// Change this to actual path from `which yt-dlp` on your server
$yt_dlp_path = '/usr/local/bin/yt-dlp';

// Build the command safely
$cmd = $yt_dlp_path . ' -f best[ext=mp4]/best -g ' . escapeshellarg($video_url) . ' 2>&1';

// Run the command
$output = shell_exec($cmd);
if ($output === null) {
    http_response_code(502);
    exit('Failed to run yt-dlp.');
}

// Extract the first valid URL from output
$url = null;
foreach (explode("\n", trim($output)) as $line) {
    $line = trim($line);
    if (preg_match('#^https?://#', $line)) {
        $url = $line;
        break;
    }
}

if (empty($url)) {
    http_response_code(502);
    exit('Failed to extract MP4 URL. Raw output: ' . htmlspecialchars($output));
}

header('Content-Type: application/json');
echo json_encode(['url' => $url]);
