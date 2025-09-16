<?php
require __DIR__ . '/init.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) {
	http_response_code(404);
	echo 'Not found';
	exit;
}
if ($v['user_id'] != $_SESSION['user_id'] && $_SESSION['user_id'] != 1) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title = substr(trim($_POST['title'] ?? ''), 0, 255);
	$description = substr(trim($_POST['description'] ?? ''), 0, 1000);
	$characters = substr(trim($_POST['characters'] ?? ''), 0, 255);
	$stmt = $pdo->prepare('UPDATE videos SET title=?, description=?, characters=? WHERE id=?');
	$stmt->execute([$title, $description, $characters, $id]);
	header('Location: view.php?id=' . $id);
	exit;
}
?>
<!doctype html>
<html>

<head>
	<title>Modification de &#34;<?= h($v['title']) ?>&#34; - SPÖÖK TUBE</title>
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
		<div class="container d-flex justify-content-between">
			<a class="navbar-brand text-dark fs-1" href="/">SPÖÖK <span class="bg-dark rounded-4 text-light p-2 px-3">TUBE</span></a>
			<div>
				Bonjour <span class="fw-bold"><?= h($current_user['username']) ?></span>
				<a href="logout.php" class="btn btn-dark rounded-6"><i class="bi bi-door-open-fill"></i> Déconnexion</a>
			</div>
		</div>
	</nav>

	<!-- main: grows to fill remaining viewport height -->
	<main class="flex-grow-1 d-flex overflow-hidden">
		<div class="container-fluid d-flex flex-column w-100">
			<div class="row flex-grow-1 gx-3">
				<div class="col d-flex h-100">
					<div class="video-wrapper w-100 d-flex">
						<video controls class="flex-grow-1" style="width:100%; height:100%; object-fit:contain; display:block;">
							<source src="serve_video.php?f=<?= urlencode($v['filename']) ?>">
							Your browser does not support HTML5 video.
						</video>
					</div>
				</div>

				<div class="col h-100 d-flex flex-column">
					<div class="row mx-0 pt-4">
						<div class="col">
							<h2 class="mb-2">Modifier &#34;<?= h($v['title']) ?>&#34;</h2>
						</div>
						<div class="col text-end">
							<button type="submit" form="edit_form" class="btn btn-dark rounded-6"><i class="bi bi-floppy-fill"></i> Enregistrer</button>
							<a class="btn btn-dark rounded-6" href="view.php?id=<?= $v['id'] ?>"><i class="bi bi-arrow-return-left"></i> Retour</a>
						</div>
					</div>
					<form method="post" id="edit_form" name="edit_form">
						<label for="title" class="form-label"><i class="bi bi-type"></i> Titre</label>
						<input type="text" class="form-control" id="title" name="title" value="<?= h($v['title']) ?>" required>
						<label for="description" class="form-label"><i class="bi bi-card-text"></i> Description</label>
						<textarea class="form-control" id="description" name="description" rows="3"><?= h($v['description']) ?></textarea>
						<label for="characters" class="form-label"><i class="bi bi-person-standing"></i> Gens</label>
						<input type="text" class="form-control" id="characters" name="characters" value="<?= h($v['characters']) ?>">
						<p><i class="bi bi-exclamation-circle"></i> Merci de mettre les <b>pseudos</b> des gens par ordre alphabétique et séparé par une vigule et un espace.</p>
					</form>
				</div>
			</div>
		</div>
	</main>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>
</body>

</html>