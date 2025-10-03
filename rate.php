<?php
require __DIR__ . '/init.php';
if (!is_logged_in()) {
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'login']);
	exit;
}
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'bad json']);
	exit;
}
$video_id = (int)($data['video_id'] ?? 0);
$rating = (int)($data['rating'] ?? 0);
if ($video_id <= 0 || $rating < 1 || $rating > 5) {
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'invalid']);
	exit;
}
// upsert rating
$stmt = $pdo->prepare('INSERT INTO ratings (user_id, video_id, rating) VALUES (?,?,?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), rated_at = NOW()');
$stmt->execute([$_SESSION['user_id'], $video_id, $rating]);
list($avg, $cnt) = avg_rating($pdo, $video_id);
header('Content-Type: application/json');
echo json_encode(['success' => true, 'avg' => $avg, 'count' => $cnt]);
