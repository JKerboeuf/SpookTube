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
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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

/**
 * Render rating as 5 stars (full / half / empty) rounded to 0.5.
 *
 * @param float|null $avg  Average rating (float) or null if no ratings
 * @param int|null   $cnt  Number of ratings (optional; shown in parentheses)
 * @param int        $max  Number of stars (default 5)
 * @return string HTML markup (safe to echo)
 */
function render_stars($avg, $cnt = null, $max = 5)
{
	// no ratings -> show empty stars and optional count
	if ($avg === null) {
		$html = '<span class="rating-stars" aria-label="No ratings">';
		for ($i = 0; $i < $max; $i++) $html .= '<i class="bi bi-star" aria-hidden="true"></i>';
		$html .= '</span>';
		if ($cnt !== null) $html .= ' <small class="text-muted">(' . intval($cnt) . ')</small>';
		return $html;
	}

	// round to nearest 0.5
	$r = round($avg * 2) / 2.0;

	// accessible label like "3.5 out of 5"
	$label = htmlspecialchars(number_format($r, ($r == (int)$r ? 0 : 1)) . ' out of ' . $max);

	$html = '<span class="rating-stars" role="img" aria-label="' . $label . '">';
	for ($i = 1; $i <= $max; $i++) {
		if ($r >= $i) {
			// full star
			$html .= '<i class="bi bi-star-fill" aria-hidden="true"></i>';
		} elseif ($r >= $i - 0.5) {
			// half star
			$html .= '<i class="bi bi-star-half" aria-hidden="true"></i>';
		} else {
			// empty star
			$html .= '<i class="bi bi-star" aria-hidden="true"></i>';
		}
	}
	$html .= '</span>';
	if ($cnt !== null) $html .= ' <small class="text-muted">(' . intval($cnt) . ')</small>';
	return $html;
}

function format_timecode($val)
{
	if ($val === null || $val === '') return '';

	// Numeric input: treat as seconds timecode
	if (is_numeric($val)) {
		$secs = (int)$val;
		if ($secs < 0) $secs = 0;
		if ($secs >= 3600) {
			// H:i:s for long timecodes
			return gmdate('H:i:s', $secs);
		}
		// m:ss (no leading zero hour)
		return gmdate('i:s', $secs);
	}

	// Otherwise assume a date/time string or timestamp
	// Try to parse it to a timestamp
	$ts = false;
	if (is_string($val)) {
		// If it looks like an integer string, convert
		if (ctype_digit($val)) {
			$ts = (int)$val;
		} else {
			$ts = strtotime($val);
		}
	} elseif ($val instanceof DateTimeInterface) {
		$ts = $val->getTimestamp();
	}

	if ($ts === false || $ts === null) return '';

	// Format as DD/MM/YYYY - HH:MM using server timezone.
	// If you prefer UTC: use gmdate() instead of date().
	return date('d/m/Y - H:i', $ts);
}
