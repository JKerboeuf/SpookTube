<?php
// save_thumb.php
require __DIR__ . '/init.php';
require_login();
$config = require __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'error' => 'method']);
	exit;
}

$video_id = (int)($_POST['video_id'] ?? 0);
if ($video_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'invalid_id']);
	exit;
}

if (empty($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'no_file']);
	exit;
}

// basic validation: limit size (e.g. 500KB)
$maxThumbBytes = 500 * 1024;
if ($_FILES['thumbnail']['size'] > $maxThumbBytes) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'thumb_too_large']);
	exit;
}

// verify video exists
$stmt = $pdo->prepare('SELECT id, user_id FROM videos WHERE id = ?');
$stmt->execute([$video_id]);
$video = $stmt->fetch();
if (!$video) {
	http_response_code(404);
	echo json_encode(['success' => false, 'error' => 'video_not_found']);
	exit;
}

// Optional: check ownership or allow only admins to set thumb.
// if ($video['user_id'] != $_SESSION['user_id']) { ... prevent or allow ... }

// Move uploaded thumbnail to thumbs dir
$ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
if (!$ext) $ext = 'webp';
$filename = bin2hex(random_bytes(8)) . '.' . $ext;
$target = rtrim($config['thumb_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

if (!is_dir($config['thumb_dir'])) mkdir($config['thumb_dir'], 0775, true);

if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $target)) {
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'save_failed']);
	exit;
}

// Optionally: you could run an image optimizer here (gd/imagemagick), but not required

// Update DB: set thumbnail field
$stmt = $pdo->prepare('UPDATE videos SET thumbnail = ? WHERE id = ?');
$stmt->execute([$filename, $video_id]);

echo json_encode(['success' => true, 'filename' => $filename]);
exit;
