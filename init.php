<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!empty($_SESSION['user_id'])) {
	$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
	$stmt->execute([$_SESSION['user_id']]);
	$current_user = $stmt->fetch();
} else {
	$current_user = null;
}
