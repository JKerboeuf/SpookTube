<?php
require __DIR__ . '/init.php';
require_login();
$config = require __DIR__ . '/config.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_FILES['video'])) {
		$errors[] = 'No file uploaded.';
	} else {
		$file = $_FILES['video'];
		if ($file['error'] !== UPLOAD_ERR_OK) $errors[] = 'Upload error code: ' . $file['error'];
		if ($file['size'] > $config['max_upload_bytes']) $errors[] = 'File too large.';
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $file['tmp_name']);
		finfo_close($finfo);
		if (strpos($mime, 'video/') !== 0) $errors[] = 'Only video files allowed (detected: ' . $mime . ').';

		if (!$errors) {
			$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
			$safeName = bin2hex(random_bytes(8)) . ($ext ? "." . $ext : '');
			$dest = $config['upload_dir'] . DIRECTORY_SEPARATOR . $safeName;
			if (!is_dir($config['upload_dir'])) mkdir($config['upload_dir'], 0775, true);
			if (!move_uploaded_file($file['tmp_name'], $dest)) {
				$errors[] = 'Failed to move uploaded file.';
			} else {
				// file_date: try to take from posted field (from JS) else null
				$file_date = null;
				if (!empty($_POST['file_date'])) {
					// expected as ms since epoch
					$ts = (int)$_POST['file_date'];
					if ($ts > 0) $file_date = date('Y-m-d H:i:s', $ts / 1000);
				}
				$title = substr(trim($_POST['title'] ?? ''), 0, 255);
				$description = substr(trim($_POST['description'] ?? ''), 0, 1000);
				$characters = substr(trim($_POST['characters'] ?? ''), 0, 255);

				$stmt = $pdo->prepare('INSERT INTO videos (user_id, title, description, characters, filename, filesize, file_date) VALUES (?,?,?,?,?,?,?)');
				$stmt->execute([$_SESSION['user_id'], $title, $description, $characters, $safeName, $file['size'], $file_date]);
				header('Location: index.php');
				exit;
			}
		}
	}
}
?>
<!doctype html>
<html>

<head>
	<title>INSERER UN DISQUE - SPÖÖK TUBE</title>
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
				<div class="col d-flex h-100 flex-column text-center text-uppercase">
					<div class="col my-5">
						<div class="btn btn-lg btn-secondary disabled rounded-5 fs-3 p-4">
							<i class="bi bi-disc-fill" style="font-size: 10rem;"></i>
							<br>
							Inserer le disque
						</div>
					</div>
				</div>

				<div class="col h-100 d-flex flex-column">
					<form method="post" enctype="multipart/form-data" id="uploadForm">
						<label for="videoInput" class="form-label"><i class="bi bi-file-earmark-play-fill"></i> Fichier vidéo</label>
						<input class="form-control form-control-lg" type="file" id="videoInput" name="video" accept="video/*" required>
						<input type="hidden" name="file_date" id="file_date_input">
						<label for="title" class="form-label"><i class="bi bi-type"></i> Titre</label>
						<input type="text" class="form-control" id="title" name="title" required>
						<label for="description" class="form-label"><i class="bi bi-card-text"></i> Description</label>
						<textarea class="form-control" id="description" name="description" rows="3" maxlength="1000"></textarea>
						<label for="characters" class="form-label"><i class="bi bi-person-standing"></i> Gens</label>
						<input type="text" class="form-control" id="characters" name="characters">
						<p><i class="bi bi-exclamation-circle"></i> Merci de mettre les <b>pseudos</b> des gens par ordre alphabétique et séparé par une vigule et un espace.</p>
						<button type="submit" class="btn btn-lg btn-dark rounded-6"><i class="bi bi-cloud-upload-fill"></i> Envoyer</button>
					</form>
				</div>
			</div>
		</div>
	</main>
	<script>
		// capture the file's last modified timestamp (from browser) and send as ms since epoch
		const input = document.getElementById('videoInput');
		const hidden = document.getElementById('file_date_input');
		input.addEventListener('change', () => {
			const f = input.files[0];
			if (f && f.lastModified)
				hidden.value = f.lastModified;
			else
				hidden.value = '';
		});
	</script>
</body>

</html>