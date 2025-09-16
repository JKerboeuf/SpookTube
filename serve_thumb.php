<?php
// serve_thumb.php - secure thumbnail serving
require __DIR__ . '/init.php';
require_login();
$f = $_GET['f'] ?? '';
$f = basename($f);
$config = require __DIR__ . '/config.php';
$path = $config['thumb_dir'] . DIRECTORY_SEPARATOR . $f;
if (!is_file($path)) { http_response_code(404); exit; }
$mime = mime_content_type($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);