<?php
// post_comment.php
require __DIR__ . '/init.php';
require_login();
header('Content-Type: application/json');

// Read JSON body (AJAX) or fallback to form POST
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    // try form POST fallback
    $data = $_POST;
}

$video_id = isset($data['video_id']) ? (int)$data['video_id'] : 0;
$content  = isset($data['content']) ? trim($data['content']) : '';
$timecode = isset($data['timecode']) && $data['timecode'] !== '' ? (int)$data['timecode'] : null;

if ($video_id <= 0 || $content === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_fields']);
    exit;
}

// limit comment length
$maxLen = 5000;
if (mb_strlen($content) > $maxLen) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'too_long']);
    exit;
}

// verify video exists
$stmt = $pdo->prepare('SELECT id FROM videos WHERE id = ?');
$stmt->execute([$video_id]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'video_not_found']);
    exit;
}

// insert
try {
    $stmt = $pdo->prepare('INSERT INTO comments (user_id, video_id, content, timecode) VALUES (?,?,?,?)');
    $stmt->execute([$_SESSION['user_id'], $video_id, $content, $timecode]);
    $comment_id = (int)$pdo->lastInsertId();

    // fetch the inserted comment with user info to render
    $stmt = $pdo->prepare('SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ? LIMIT 1');
    $stmt->execute([$comment_id]);
    $c = $stmt->fetch();

    if (!$c) throw new Exception('Could not fetch comment after insert');

    // helper for escaping
    function h_local($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

    // format timecode nicely like 1:23 or 01:02:03
    function fmt_timecode($secs) {
        if ($secs === null) return '';
        $secs = (int)$secs;
        if ($secs < 0) $secs = 0;
        if ($secs >= 3600) return gmdate('H:i:s', $secs);
        return gmdate('i:s', $secs);
    }

    // prepare HTML snippet (safe: we escape content)
    $timecodeHtml = $c['timecode'] !== null
		? '<button class="timecode-link btn btn-sm btn-dark rounded-6" data-seconds="' . (int)$c['timecode'] . '"><i class="bi bi-play-fill"></i> ' . h(fmt_timecode($c['timecode'])) . '</button> '
        : '';

    $html = '<li class="list-group-item" data-comment-id="' . $c['id'] . '">'
        . '<div class="d-flex justify-content-between">'
        . '<div><strong>' . h_local($c['username']) . '</strong> <small class="text-muted">' . h(format_timecode($c['created_at'])) . '</small></div>'
		. '<button class="btn btn-sm btn-dark rounded-6 delete-comment-btn" data-comment-id="' . (int)$c['id'] . '" title="Delete comment">'
		. '<i class="bi bi-trash3-fill"></i></button>'
        . '</div>'
        . '<div class="mt-2 comment-content">'
        . ($timecodeHtml ? $timecodeHtml : '')
		. '<div class="d-inline">' . nl2br(h_local($c['content'])) . '</div>'
        . '</div></li>';

    echo json_encode(['success' => true, 'html' => $html]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log('post_comment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'server_error']);
    exit;
}
