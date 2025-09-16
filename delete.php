<?php
require __DIR__ . '/init.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo 'Method not allowed';
	exit;
}
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
	http_response_code(400);
	echo 'Invalid id';
	exit;
}
$stmt = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) {
	http_response_code(404);
	echo 'Not found';
	exit;
}
if ($v['user_id'] != $_SESSION['user_id'] && $_SESSION['user_id'] != 1) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$config = require __DIR__ . '/config.php';
try {
	$pdo->beginTransaction();
	$stmt = $pdo->prepare('DELETE FROM videos WHERE id = ?');
	$stmt->execute([$id]);
	$pdo->commit();
	$fpath = $config['upload_dir'] . DIRECTORY_SEPARATOR . $v['filename'];
	if (is_file($fpath)) @unlink($fpath);
	if (!empty($v['thumbnail'])) {
		$tpath = $config['thumb_dir'] . DIRECTORY_SEPARATOR . $v['thumbnail'];
		if (is_file($tpath)) @unlink($tpath);
	}
	header('Location: index.php');
	exit;
} catch (Exception $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	echo 'Error: ' . h($e->getMessage());
}
