<?php
require __DIR__ . '/init.php';
if (is_logged_in()) {
	header('Location: index.php');
	exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$u = $_POST['username'] ?? '';
	$p = $_POST['password'] ?? '';
	$stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ?');
	$stmt->execute([$u]);
	$user = $stmt->fetch();
	if ($user && password_verify($p, $user['password'])) {
		$_SESSION['user_id'] = $user['id'];
		header('Location: index.php');
		exit;
	} else {
		$error = 'Identifiants invalides.';
	}
}
?>
<!doctype html>
<html>

<head>
	<title>SPÖÖK TUBE</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" type="image/x-icon" href="favicon.ico">
	<link rel="stylesheet" href="style.css">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
		rel="stylesheet"
		integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
		crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>


<body class="d-flex flex-column min-vh-100 bg-light">
	<nav class="navbar bg-white shadow-sm flex-shrink-0">
		<div class="container d-flex justify-content-center">
			<a class="navbar-brand text-dark fs-1" href="/">SPÖÖK <span class="bg-dark rounded-4 text-light p-2 px-3">TUBE</span></a>
		</div>
	</nav>

	<div class="container text-center my-2">
		<div class="w-50 mx-auto">
			<?php if ($error): ?>
				<div class="alert alert-danger" role="alert">
					<?= h($error) ?>
				</div>
			<?php endif; ?>
			<form method="post">
				<label for="username" class="form-label"><i class="bi bi-person-fill"></i> Utilisateur</label>
				<input type="text" class="form-control" id="username" name="username" required>
				<label for="password" class="form-label"><i class="bi bi-lock-fill"></i> Mot de passe</label>
				<input type="password" class="form-control" id="password" name="password" required>
				<button type="submit" class="btn btn-lg btn-dark mt-3">Connexion</button>
			</form>
		</div>
	</div>
</body>

</html>