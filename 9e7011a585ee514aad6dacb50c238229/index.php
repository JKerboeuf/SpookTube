<?php
// index.php
// Put this file in any folder you want to protect from directory browsing.
//
// Defaults: blocks everyone except localhost. Edit $ALLOWED_IPS or $REDIRECT below.
//
// - $ALLOWED_IPS: array of IPs allowed to see a simple message (use your public IP if you
//   want to access the folder remotely).
// - $REDIRECT: if set to a URL (eg '/'), non-allowed visitors will be redirected there.
// - $LOG: whether to log access attempts to a dotfile in the same folder.
//
// NOTE: If you want strict lock-down for everyone, set $ALLOWED_IPS = [];

$ALLOWED_IPS = []; // edit: add your IP(s) if you want to allow them
$REDIRECT = '/';                     // e.g. '/' to redirect blocked visitors to site root (or keep null)
$LOG = false;                          // write an access attempt log file (.access_block.log)
$LOG_FILE = __DIR__ . '/.access_block.log';

// Get client IP (try common headers then REMOTE_ADDR)
function client_ip(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // may contain multiple IPs, use the first
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$ip = client_ip();
$allowed = in_array($ip, $ALLOWED_IPS, true);

// Logging (best-effort, don't expose sensitive info)
if (!$allowed && $LOG) {
    $req = $_SERVER['REQUEST_URI'] ?? '-';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '-';
    $line = sprintf("[%s] %s %s \"%s\"\n", date('Y-m-d H:i:s'), $ip, $req, $ua);
    @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// Common security headers and response
$statusHeader = ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden';
if (!$allowed) {
    header($statusHeader, true, 403);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');

    if (!empty($REDIRECT)) {
        // safe redirect (simple guard)
        header('Location: ' . $REDIRECT);
        exit;
    }

    // Minimal 403 page (no revealing info)
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>403 - Forbidden</title></head><body style="font-family:Arial,sans-serif;margin:40px;"><h1>403 - Forbidden</h1><p>Access denied.</p></body></html>';
    exit;
}

// If allowed, show a tiny neutral message so you know the file is there.
// You can change this to redirect to parent or show nothing.
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Protected</title></head><body style="font-family:Arial,sans-serif;margin:40px;"><p>Directory protected.</p></body></html>';
exit;