<?php
// delete_comment.php
require __DIR__ . '/init.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'error' => 'method']);
	exit;
}

// accept JSON body or form POST
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST;

$comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
if ($comment_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'invalid_id']);
	exit;
}
try {
	$stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ? LIMIT 1');
	$stmt->execute([$comment_id]);
	$owner = $stmt->fetchColumn();
	if (!$owner) {
		http_response_code(404);
		echo json_encode(['success' => false, 'error' => 'not_found']);
		exit;
	}
	if ((int)$owner !== (int)$_SESSION['user_id'] && $_SESSION['user_id'] != 1) {
		http_response_code(403);
		echo json_encode(['success' => false, 'error' => 'forbidden']);
		exit;
	}
	$stmt = $pdo->prepare('DELETE FROM comments WHERE id = ?');
	$stmt->execute([$comment_id]);
	echo json_encode(['success' => true]);
	exit;
} catch (Exception $e) {
	http_response_code(500);
	error_log('delete_comment error: ' . $e->getMessage());
	echo json_encode(['success' => false, 'error' => 'server_error']);
	exit;
}
