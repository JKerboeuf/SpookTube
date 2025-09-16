<?php
// toggle_favorite.php
require __DIR__ . '/init.php';
require_login();
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$video_id = isset($data['video_id']) ? (int)$data['video_id'] : 0;
if ($video_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_id']);
    exit;
}

try {
    // Check if already favorited
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND video_id = ?');
    $stmt->execute([$_SESSION['user_id'], $video_id]);
    $exists = (bool)$stmt->fetchColumn();

    if ($exists) {
        // remove favorite
        $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND video_id = ?');
        $stmt->execute([$_SESSION['user_id'], $video_id]);
        echo json_encode(['success' => true, 'fav' => false]);
        exit;
    } else {
        // insert favorite
        $stmt = $pdo->prepare('INSERT INTO favorites (user_id, video_id) VALUES (?,?)');
        $stmt->execute([$_SESSION['user_id'], $video_id]);
        echo json_encode(['success' => true, 'fav' => true]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('toggle_favorite error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'server_error']);
    exit;
}
