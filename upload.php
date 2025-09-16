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




	<div class="modal fade bg-light" id="dupModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable bg-light rounded-6">
			<div class="modal-content rounded-5 shadow">
				<div class="modal-header bg-white">
					<h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Vidéo potentiellement dupliquée</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>

				<div class="modal-body bg-light">
					<div class="container-fluid">
						<div class="row gy-3">
							<!-- Left: Local preview -->
							<div class="col-md-5">
								<h6>Ta video</h6>
								<div class="border p-2 rounded-6 bg-white">
									<video id="localPreviewVideo" controls style="width:100%; height:240px; background:black; display:block;">
										Your browser does not support the video element.
									</video>
									<div id="localInfo" class="mt-2 text-muted"></div>
								</div>
							</div>

							<!-- Right: Matches list -->
							<div class="col-md-7">
								<h6>Vidéos similaires</h6>
								<div id="dupMatches" class="list-group rounded-6"></div>
								<p class="mt-2 text-muted">Si l'une de ces vidéos est la même que la votre, merci d'annuler votre publication.</p>
							</div>
						</div>
					</div>
				</div>

				<div class="modal-footer bg-white">
					<button type="button" id="cancelUploadBtn" class="btn btn-dark rounded-6" data-bs-dismiss="modal"><i class="bi bi-x-circle-fill"></i> Annuler</button>
					<button type="button" id="continueUploadBtn" class="btn btn-outline-dark rounded-6"><i class="bi bi-check-circle-fill"></i> Publier quand même</button>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>

	<script>
		(function() {
			const form = document.getElementById('uploadForm');
			const fileInput = document.getElementById('videoInput');
			const dupModalEl = document.getElementById('dupModal');
			const dupMatchesEl = document.getElementById('dupMatches');
			const continueBtn = document.getElementById('continueUploadBtn');
			const cancelBtn = document.getElementById('cancelUploadBtn');
			const localVideo = document.getElementById('localPreviewVideo');
			const localInfo = document.getElementById('localInfo');

			// Bootstrap modal instance
			let dupModal = null;
			if (typeof bootstrap !== 'undefined') {
				dupModal = new bootstrap.Modal(dupModalEl, {
					keyboard: true
				});
			}

			// keep track of object URL to revoke it when done
			let localObjectUrl = null;

			// helper: format bytes
			function formatBytes(bytes) {
				if (bytes === 0) return '0 B';
				const units = ['B', 'KB', 'MB', 'GB', 'TB'];
				const i = Math.floor(Math.log(bytes) / Math.log(1024));
				return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
			}

			// simple HTML escape
			function escapeHtml(s) {
				if (s === null || s === undefined) return '';
				return String(s).replace(/[&<>"']/g, function(m) {
					return ({
						'&': '&amp;',
						'<': '&lt;',
						'>': '&gt;',
						'"': '&quot;',
						"'": '&#39;'
					})[m];
				});
			}

			// populate matches into the list (no select, just view link)
			function renderMatches(matches) {
				dupMatchesEl.innerHTML = '';
				if (!matches.length) {
					dupMatchesEl.innerHTML = '<div class="alert alert-info mb-0">No similar videos found on the server.</div>';
					return;
				}
				matches.forEach(m => {
					const el = document.createElement('div');
					el.className = 'list-group-item d-flex gap-3 align-items-start';
					console.log(encodeURIComponent(m.filename));
					const thumbHtml = '<video controls src="serve_video.php?f=' + encodeURIComponent(m.filename) + '" style="width:210px;height:210px;object-fit:cover;border-radius:4px;">';
					el.innerHTML = `
						<div style="flex:0 0 1">${thumbHtml}</div>
						<div style="flex:1">
							<div class="fw-bold">${escapeHtml(m.title)}</div>
							<div class="text-muted small">By ${escapeHtml(m.username)}
							<br>${escapeHtml(m.file_date)}
							<br>${escapeHtml(m.filesize)} octets</div>
							<div class="mt-2"><a class="btn btn-dark rounded-6" href="${escapeHtml(m.url)}" target="_blank" rel="noopener">Ouvrir <i class="bi bi-box-arrow-up-right"></i></a></div>
						</div>
					`;
					dupMatchesEl.appendChild(el);
				});
			}

			// Clear local preview and revoke object URL
			function clearLocalPreview() {
				if (localObjectUrl) {
					try {
						URL.revokeObjectURL(localObjectUrl);
					} catch (e) {}
					localObjectUrl = null;
				}
				localVideo.pause();
				localVideo.removeAttribute('src');
				localVideo.load();
				localInfo.textContent = '';
			}

			// intercept submit to perform pre-check
			form.addEventListener('submit', function(ev) {
				ev.preventDefault();
				const f = fileInput.files[0];
				if (!f) {
					alert('Choose a file first');
					return;
				}

				const fileDateMs = f.lastModified || 0;
				const filesize = f.size || 0;

				// if file date not available, skip pre-check
				if (!fileDateMs) {
					form.submit();
					return;
				}

				// disable buttons while checking
				continueBtn.disabled = true;
				cancelBtn.disabled = true;

				fetch('duplicate_check.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						file_date: fileDateMs,
						filesize: filesize
					})
				}).then(r => r.json()).then(data => {
					// if something goes wrong, proceed with upload
					if (!data || !data.success) {
						form.submit();
						return;
					}

					const matches = data.matches || [];

					// prepare local preview (object URL)
					try {
						clearLocalPreview();
						localObjectUrl = URL.createObjectURL(f);
						localVideo.src = localObjectUrl;
						localInfo.innerHTML = `${f.name}<br>${(new Date(fileDateMs)).toLocaleString()}<br>${f.size} octets`;
					} catch (e) {
						localInfo.innerHTML = 'Preview not available';
					}

					// render matches and show modal only if matches found
					if (!matches.length) {
						// no duplicates -> proceed with upload
						// cleanup object URL after a short delay (let browser keep playing if user wishes)
						setTimeout(clearLocalPreview, 2000);
						form.submit();
						return;
					}

					renderMatches(matches);

					// enable modal buttons and show it
					continueBtn.disabled = false;
					cancelBtn.disabled = false;
					if (dupModal) dupModal.show();
				}).catch(err => {
					console.error(err);
					// fallback: submit if pre-check fails
					form.submit();
				});
			});

			// Continue upload -> hide modal and submit form
			continueBtn.addEventListener('click', function() {
				if (dupModal) dupModal.hide();
				// small delay to allow modal to close smoothly
				setTimeout(() => {
					form.submit();
				}, 150);
			});

			// Cancel upload -> clear file input, revoke preview, and close modal
			cancelBtn.addEventListener('click', function() {
				try {
					fileInput.value = '';
				} catch (e) {
					console.warn(e);
				}
				clearLocalPreview();
				// modal will auto-hide due to data-bs-dismiss on the button
			});

			// also clean up when modal fully hidden (revoke url so memory freed)
			if (dupModalEl) {
				dupModalEl.addEventListener('hidden.bs.modal', function() {
					// do not revoke immediately if user still playing - but we cleared on cancel/continue
					// safe to revoke here as a final cleanup
					clearLocalPreview();
				});
			}

		})();
	</script>




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