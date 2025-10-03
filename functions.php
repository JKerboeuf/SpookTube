<?php
function is_logged_in()
{
	return !empty($_SESSION['user_id']);
}

function require_login()
{
	if (!is_logged_in()) {
		header('Location: login.php');
		exit;
	}
}

function h($s)
{
	return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function avg_rating($pdo, $video_id)
{
	$stmt = $pdo->prepare('SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM ratings WHERE video_id = ?');
	$stmt->execute([$video_id]);
	$r = $stmt->fetch();
	return [$r['avg'] ? round($r['avg'], 2) : null, (int)$r['cnt']];
}

function format_date_ddmmyyyy($datetime)
{
	if (!$datetime) return 'N/A';
	$ts = strtotime($datetime);
	if (!$ts) return 'N/A';
	return date('d/m/Y', $ts);
}

function render_stars($avg, $cnt = null, $max = 5)
{
	if ($avg === null) {
		$html = '<span class="rating-stars" aria-label="No ratings">';
		for ($i = 0; $i < $max; $i++) $html .= '<i class="bi bi-star" aria-hidden="true"></i>';
		$html .= '</span>';
		if ($cnt !== null) $html .= ' <small class="text-muted">(' . intval($cnt) . ')</small>';
		return $html;
	}
	$r = round($avg * 2) / 2.0;
	$label = htmlspecialchars(number_format($r, ($r == (int)$r ? 0 : 1)) . ' out of ' . $max);
	$html = '<span class="rating-stars" role="img" aria-label="' . $label . '">';
	for ($i = 1; $i <= $max; $i++) {
		if ($r >= $i) $html .= '<i class="bi bi-star-fill" aria-hidden="true"></i>';
		elseif ($r >= $i - 0.5) $html .= '<i class="bi bi-star-half" aria-hidden="true"></i>';
		else $html .= '<i class="bi bi-star" aria-hidden="true"></i>';
	}
	$html .= '</span>';
	if ($cnt !== null) $html .= ' <small class="text-muted">(' . intval($cnt) . ')</small>';
	return $html;
}

function format_timecode($val)
{
	if ($val === null || $val === '') return '';
	if (is_numeric($val)) {
		$secs = (int)$val;
		if ($secs < 0) $secs = 0;
		if ($secs >= 3600) return gmdate('H:i:s', $secs);
		return gmdate('i:s', $secs);
	}
	$ts = false;
	if (is_string($val)) {
		if (ctype_digit($val)) $ts = (int)$val;
		else $ts = strtotime($val);
	} elseif ($val instanceof DateTimeInterface) {
		$ts = $val->getTimestamp();
	}
	if ($ts === false || $ts === null) return '';
	return date('d/m/Y - H:i', $ts);
}
