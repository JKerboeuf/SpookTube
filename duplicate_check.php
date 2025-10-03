<?php
// duplicate_check.php
// Accepts JSON POST with { file_date: <ms>, filesize: <bytes> } and returns JSON array of candidate matches.
// Requires login.

require __DIR__ . '/init.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'login']);
	exit;
}

// read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'invalid_json']);
	exit;
}

$fileDateMs = isset($data['file_date']) ? (int)$data['file_date'] : 0;
$filesize = isset($data['filesize']) ? (int)$data['filesize'] : 0;

if ($fileDateMs <= 0 || $filesize <= 0) {
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'missing_fields']);
	exit;
}

// convert ms -> mysql DATETIME string
$fileDate = date('Y-m-d H:i:s', $fileDateMs / 1000);

// Matching parameters (tune these if desired)
$timeWindowSeconds = 5 * 60; // +/- 5 minutes
// filesize tolerance: allow exact OR small diffs. We use max(1000 bytes, 1% of file size)
$absTolerance = max(1000, (int)round($filesize * 0.01));

// Prepare SQL: find videos with file_date within ±5 minutes, and filesize within tolerance
$sql = "
SELECT v.id, v.title, v.characters, v.file_date, v.filesize, u.username, v.filename
FROM videos v
JOIN users u ON v.user_id = u.id
WHERE v.file_date BETWEEN DATE_SUB(?, INTERVAL {$timeWindowSeconds} SECOND) AND DATE_ADD(?, INTERVAL {$timeWindowSeconds} SECOND)
  AND ABS(v.filesize - ?) <= ?
ORDER BY ABS(TIMESTAMPDIFF(SECOND, v.file_date, ?)) ASC
LIMIT 20
";

try {
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$fileDate, $fileDate, $filesize, $absTolerance, $fileDate]);
	$rows = $stmt->fetchAll();
	$out = [];
	foreach ($rows as $r) {
		$out[] = [
			'id' => (int)$r['id'],
			'title' => htmlspecialchars($r['title'], ENT_QUOTES),
			'characters' => htmlspecialchars($r['characters'], ENT_QUOTES),
			'file_date' => $r['file_date'],
			'filesize' => (int)$r['filesize'],
			'username' => htmlspecialchars($r['username'], ENT_QUOTES),
			'filename' => $r['filename'],
			'url' => 'view.php?id=' . (int)$r['id'],
		];
	}
	echo json_encode(['success' => true, 'matches' => $out]);
} catch (Exception $e) {
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'db_error', 'msg' => $e->getMessage()]);
}
