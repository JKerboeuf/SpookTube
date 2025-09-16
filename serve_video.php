<?php
// serve_video.php - supports HTTP Range requests
require __DIR__ . '/init.php';
require_login();

$f = $_GET['f'] ?? '';
$f = basename($f); // prevent traversal
$config = require __DIR__ . '/config.php';
$path = $config['upload_dir'] . DIRECTORY_SEPARATOR . $f;

if (!is_file($path)) {
	http_response_code(404);
	exit;
}

$size = filesize($path);
$mime = mime_content_type($path) ?: 'application/octet-stream';
$fp = fopen($path, 'rb');
if (!$fp) {
	http_response_code(500);
	exit;
}

// Defaults
$start = 0;
$end = $size - 1;
$httpStatus = 200;
$length = $size;
$headers = [];

// Check for Range header
if (isset($_SERVER['HTTP_RANGE'])) {
	// Example: "Range: bytes=12345-"
	if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
		$rangeStart = $matches[1] !== '' ? intval($matches[1]) : null;
		$rangeEnd   = $matches[2] !== '' ? intval($matches[2]) : null;

		if ($rangeStart === null && $rangeEnd !== null) {
			// suffix-byte-range-spec, not common for video
			$start = max(0, $size - $rangeEnd);
			$end = $size - 1;
		} elseif ($rangeStart !== null && $rangeEnd === null) {
			$start = $rangeStart;
			$end = $size - 1;
		} elseif ($rangeStart !== null && $rangeEnd !== null) {
			$start = $rangeStart;
			$end = min($rangeEnd, $size - 1);
		}
		if ($start > $end || $start >= $size) {
			header("HTTP/1.1 416 Range Not Satisfiable");
			header("Content-Range: bytes */$size");
			exit;
		}
		$httpStatus = 206;
		$length = $end - $start + 1;
		fseek($fp, $start);
	}
}

// Send headers
if ($httpStatus === 206) {
	header('HTTP/1.1 206 Partial Content');
} else {
	header('HTTP/1.1 200 OK');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
if ($httpStatus === 206) {
	header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
}

// Optional: cache headers (adjust for your needs)
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

// Output the requested range in chunks
$bufferSize = 1024 * 1024; // 1MB per chunk
$bytesLeft = $length;

while ($bytesLeft > 0 && !feof($fp)) {
	$read = min($bufferSize, $bytesLeft);
	$data = fread($fp, $read);
	echo $data;
	flush();
	$bytesLeft -= strlen($data);
}
fclose($fp);
exit;
