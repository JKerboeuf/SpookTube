<?php
require __DIR__ . '/init.php';
require_login();


// search and filter params
$q = trim($_GET['q'] ?? '');
$filterChar = trim($_GET['character'] ?? '');


// build query
$sql = 'SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id';
$conds = [];
$params = [];
if ($q !== '') {
	$conds[] = 'v.title LIKE ?';
	$params[] = "%$q%";
}
if ($filterChar !== '') {
	$conds[] = 'v.characters LIKE ?';
	$params[] = "%$filterChar%";
}
if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
$sql .= ' ORDER BY v.file_date DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll();
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

<body class="bg-light">
	<nav class="navbar bg-white shadow-sm">
		<div class="container d-flex justify-content-between">
			<a class="navbar-brand text-dark fs-1" href="/">SPÖÖK <span class="bg-dark rounded-4 text-light p-2 px-3">TUBE</span></a>
			<div>
				Bonjour <span class="fw-bold"><?= h($current_user['username']) ?></span> <a href="logout.php" class="btn btn-dark rounded-6"><i class="bi bi-door-open-fill"></i> Déconnexion</a>
			</div>
		</div>
	</nav>
	<div class="container bg-light grid">
		<div class="row p-2 my-4 rounded-5 shadow bg-white">
			<div class="col m-auto fs-2 text-uppercase text-center">
				Publier une vidéo
			</div>
			<div class="col my-5 text-center text-uppercase">
				<a href="upload.php" class="btn btn-lg btn-secondary rounded-5 fs-3 p-4">
					<i class="bi bi-disc-fill fs-1"></i>
					<br>
					Inserer le disque
				</a>
			</div>
		</div>
		<div class="row mx-2">
			<div class="col">
				<h2 class="text-uppercase"><i class="bi bi-camera-video-fill"></i> videos</h2>
			</div>
			<div class="col m-auto">
				<form method="get" class="input-group shadow-sm rounded-6">
					<input type="text" class="form-control rounded-start-6" placeholder="Chercher par titre" name="q" value="<?= h($q) ?>">
					<input type="text" class="form-control" placeholder="Chercher par gens" name="character" value="<?= h($filterChar) ?>">
					<button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
					<a href="index.php" class="btn btn-dark rounded-end-6" type="button"><i class="bi bi-x"></i></a>
				</form>
			</div>
		</div>

		<div class="row">
			<?php if (!$videos): ?>
				<h1 class="text-center text-uppercase my-5">
					No videos found
					<br class="my-5">
					<i class="bi bi-camera-video-off-fill fs-1"></i>
				</h1>
			<?php else: ?>
				<div class="row row-cols-4 g-5 mt-1">
					<?php foreach ($videos as $v): list($avg, $cnt) = avg_rating($pdo, $v['id']); ?>
						<div class="col mt-1">
							<div class="card card-white shadow-sm border-0 rounded-5">
								<video muted class="rounded-top-5">
									<source src="serve_video.php?f=<?= urlencode($v['filename']) ?>">
									Your browser does not support HTML5 video.
								</video>
								<div class="card-body">
									<a href="view.php?id=<?= $v['id'] ?>" class="card-title h5 text-decoration-none stretched-link"><?= h($v['title']) ?></a>
									<div class="chars"><i class="bi bi-person-standing"></i><?= h($v['characters']) ?></div>
									<div class="rating"><?= render_stars($avg, $cnt) ?></div>
									<div class="filedate"><i class="bi bi-calendar3"></i> <?= h(format_date_ddmmyyyy($v['file_date'])) ?></div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<nav class="my-4">
		<ul class="pagination justify-content-center">
			<li class="page-item disabled">
				<a class="page-link text-black">Previous</a>
			</li>
			<li class="page-item"><a class="page-link text-black" href="#">1</a></li>
			<li class="page-item"><a class="page-link text-black" href="#">2</a></li>
			<li class="page-item"><a class="page-link text-black" href="#">3</a></li>
			<li class="page-item">
				<a class="page-link text-black" href="#">Next</a>
			</li>
		</ul>
	</nav>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>
</body>

</html>