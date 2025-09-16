<?php
// serve_video.php - simple access control for raw video file serving
require __DIR__ . '/init.php';
require_login();
$f = $_GET['f'] ?? '';
$f = basename($f); // prevent path traversal
$config = require __DIR__ . '/config.php';
$path = $config['upload_dir'] . DIRECTORY_SEPARATOR . $f;
if (!is_file($path)) {
	http_response_code(404);
	exit;
}
$mime = mime_content_type($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
// Let PHP read the file
readfile($path);
