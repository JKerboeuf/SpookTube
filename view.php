<?php
require __DIR__ . '/init.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id WHERE v.id = ?');
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) {
	http_response_code(404);
	echo 'Not found';
	exit;
}
list($avg, $cnt) = avg_rating($pdo, $id);
$stmt = $pdo->prepare('SELECT rating FROM ratings WHERE video_id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$myRating = $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND video_id = ?');
$stmt->execute([$_SESSION['user_id'], $v['id']]);
$isFav = (bool)$stmt->fetchColumn();
?>
<!doctype html>
<html class="h-100">

<head>
	<title><?= h($v['title']) ?> - SPÖÖK TUBE</title>
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
							<h2 class="mb-2"><?= h($v['title']) ?></h2>
						</div>
						<div class="col text-end">
							<?php if ($v['user_id'] == $_SESSION['user_id'] || $_SESSION['user_id'] == 1): ?>
								<a class="btn btn-dark rounded-6" href="edit.php?id=<?= $v['id'] ?>"><i class="bi bi-pencil-fill"></i> Modifier</a>
								<form method="post" action="delete.php" onsubmit="return confirm('Supprimer la vidéo? La suppression sera définitive.');" style="display:inline">
									<input type="hidden" name="id" value="<?= $v['id'] ?>">
									<button class="btn btn-dark rounded-6" type="submit"><i class="bi bi-trash3-fill"></i> Supprimer</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
					<div class="row mx-0 py-1 justify-content-around text-center">
						<div class="col">
							<div class="bg-black rounded-6 mx-auto text-white p-1 px-2" style="width: fit-content;"><i class="bi bi-person-up"></i> Posté par <span class="fw-bold"><?= h($v['username']) ?></span></div>
						</div>
						<div class="col">
							<div class="bg-black rounded-6 mx-auto text-white p-1 px-2" style="width: fit-content;"><i class="bi bi-calendar3"></i> Subi le <span class="fw-bold"><?= h(format_date_ddmmyyyy($v['file_date'])) ?></span></div>
						</div>
						<div class="col">
							<div class="bg-black rounded-6 mx-auto text-white p-1 px-2" style="width: fit-content;"><i class="bi bi-person-standing"></i> En compagnie de <span class="fw-bold"><?= h($v['characters']) ?></span></div>
						</div>
					</div>
					<div class="row mx-0 pt-2 text-center">
						<div class="col my-auto">
							<div id="avgRating" data-avg="<?= htmlspecialchars($avg ?? '', ENT_QUOTES) ?>"
								data-count="<?= intval($cnt) ?>">
								Note moyenne : <?= render_stars($avg, $cnt) ?>
							</div>
						</div>
						<div class="col my-auto">
							<div class="d-flex flex-row justify-content-center align-items-center">
								<div id="userRating" role="radiogroup" style="display:inline-block"></div>
								<div id="rateResult" class="ms-2"></div>
							</div>
						</div>
						<div class="col my-auto">
							<?php if ($isFav): ?>
								<button type="button" id="onlyFavoritesSwitch" data-video-id="<?= (int)$v['id'] ?>" aria-pressed="true" class="btn btn-outline-dark active rounded-6"><i class="bi bi-heart-fill"></i> Favori</button>
							<?php else: ?>
								<button type="button" id="onlyFavoritesSwitch" data-video-id="<?= (int)$v['id'] ?>" aria-pressed="false" class="btn btn-outline-dark rounded-6"><i class="bi bi-heart"></i> Ajouter aux favoris</button>
							<?php endif; ?>
						</div>
					</div>
					<hr class="mx-2">
					<div class="row mx-0 px-3">
						<?= nl2br(h($v['description'])) ?>
					</div>
					<hr class="mx-2">
					<div class="row mx-0">
						<div class="col">
							<h3 class="mb-2 text-uppercase">Commentaires</h3>
							<div>ne fonctionnent pas encore</div>
							<form action="">
								<input type="text">
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>

	<style>
		/* interactive user stars */
		.user-star {
			cursor: pointer;
			color: #000;
		}

		.user-star.filled {
			color: #000;
		}

		/* filled (gold) */
		.user-star.hover {
			color: #000;
		}

		/* hover preview color */
		#avgRating .bi-star {
			color: #000;
		}

		/* empty avg star style */
		.rating-toast {
			color: #000;
		}
	</style>

	<script>
		(function() {
			// delegate clicks on favorite buttons (works for multiple buttons)
			btn = document.getElementById("onlyFavoritesSwitch");
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				const vid = btn.dataset.videoId;
				if (!vid) return;

				// optimistic UI: toggle icon while request in progress
				const wasFav = btn.getAttribute('aria-pressed') === 'true';

				fetch('toggle_favorite.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						video_id: parseInt(vid, 10)
					}),
					credentials: 'same-origin'
				}).then(r => r.json()).then(data => {
					if (!data || !data.success) {
						// revert on error
						if (wasFav) {
							btn.setAttribute('aria-pressed', 'true');
							btn.classList.add('active');
						} else {
							btn.setAttribute('aria-pressed', 'false');
							btn.classList.remove('active');
						}
						alert(data && data.error ? data.error : 'Could not toggle favorite');
					} else {
						if (wasFav) {
							btn.setAttribute('aria-pressed', 'false');
							btn.classList.remove('active');
							btn.innerHTML = '<i class="bi bi-heart"></i> Retiré des favoris';
						} else {
							btn.setAttribute('aria-pressed', 'true');
							btn.classList.add('active');
							btn.innerHTML = '<i class="bi bi-heart-fill"></i> Ajouté aux favoris';
						}
					}
				}).catch(err => {
					console.error(err);
					// revert UI
					if (wasFav) {
						btn.setAttribute('aria-pressed', 'true');
						btn.classList.add('active');
					} else {
						btn.setAttribute('aria-pressed', 'false');
						btn.classList.remove('active');
					}
					alert('Network error');
				});
			});
		})();
	</script>

	<script>
		(function() {
			const vid = <?= json_encode((int)$id) ?>;
			let currentUserRating = <?= json_encode($myRating ? (int)$myRating : 0) ?>;
			const userRatingEl = document.getElementById('userRating');
			const avgRatingEl = document.getElementById('avgRating');
			const rateResultEl = document.getElementById('rateResult');

			// helper to build icon html
			function iconHtml(cls) {
				return '<i class="bi ' + cls + '"></i>';
			}

			// render average stars (rounded to 0.5) using Bootstrap Icons
			function renderAvgStars(avg, count) {
				if (avg === null || avg === '' || isNaN(avg)) {
					// no ratings -> show 5 empty stars and count if any
					let html = '';
					for (let i = 0; i < 5; i++) html += iconHtml('bi-star');
					if (typeof count !== 'undefined') html += ' <small class="text-muted">(' + count + ')</small>';
					return html;
				}
				// round to nearest 0.5
				let r = Math.round(avg * 2) / 2;
				let html = '';
				for (let i = 1; i <= 5; i++) {
					if (r >= i) html += iconHtml('bi-star-fill');
					else if (r >= i - 0.5) html += iconHtml('bi-star-half');
					else html += iconHtml('bi-star');
				}
				if (typeof count !== 'undefined') html += ' <small class="text-muted">(' + count + ')</small>';
				return html;
			}

			// render user's interactive stars (filled up to currentUserRating)
			function renderUserStars() {
				userRatingEl.innerHTML = 'Ta note : '; // clear
				for (let i = 1; i <= 5; i++) {
					const span = document.createElement('span');
					span.className = 'user-star' + (i <= currentUserRating ? ' filled' : '');
					span.setAttribute('role', 'radio');
					span.setAttribute('aria-checked', i === currentUserRating ? 'true' : 'false');
					span.setAttribute('tabindex', '0');
					span.dataset.value = i;
					span.innerHTML = '<i class="bi ' + (i <= currentUserRating ? 'bi-star-fill' : 'bi-star') + '"></i>';
					userRatingEl.appendChild(span);
				}
				attachUserStarListeners();
			}

			// attach events to .user-star elements
			function attachUserStarListeners() {
				const stars = userRatingEl.querySelectorAll('.user-star');
				stars.forEach(star => {
					const v = parseInt(star.dataset.value, 10);

					// hover preview
					star.addEventListener('mouseenter', () => {
						stars.forEach(s => s.classList.toggle('hover', parseInt(s.dataset.value, 10) <= v));
					});
					star.addEventListener('mouseleave', () => {
						stars.forEach(s => s.classList.remove('hover'));
					});

					// keyboard accessibility: Enter or Space triggers click
					star.addEventListener('keydown', (ev) => {
						if (ev.key === 'Enter' || ev.key === ' ') {
							ev.preventDefault();
							star.click();
						}
					});

					// click => send rating
					star.addEventListener('click', () => {
						submitRating(v, star);
					});
				});
			}

			// submit rating via fetch to rate.php
			let pending = false;

			function submitRating(value, clickedStar) {
				if (pending) return;
				pending = true;
				// give immediate UI feedback
				rateResultEl.innerHTML = iconHtml('bi-hourglass-split');

				fetch('rate.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify({
							video_id: vid,
							rating: value
						})
					}).then(resp => resp.json())
					.then(data => {
						if (data && data.success) {
							// update average and count
							const newAvg = data.avg;
							const newCount = data.count;
							avgRatingEl.innerHTML = 'Note moyenne : ' + renderAvgStars(newAvg, newCount);
							// update user's stars
							currentUserRating = value;
							renderUserStars();
							rateResultEl.innerHTML = iconHtml('bi-check-circle-fill text-success');
						} else {
							rateResultEl.innerHTML = '<span class="rating-toast text-danger">' + (data.error || 'Erreur') + '</span>';
						}
					})
					.catch(err => {
						console.error(err);
						rateResultEl.innerHTML = '<span class="rating-toast text-danger">Erreur réseau</span>';
					})
					.finally(() => {
						pending = false;
						setTimeout(() => rateResultEl.innerHTML = '', 2000);
					});
			}

			// initialize: render the interactive stars and ensure avg is present
			(function init() {
				// ensure avgRatingEl has data available (if not, try reading data attributes)
				const avgData = avgRatingEl.dataset.avg;
				const cntData = avgRatingEl.dataset.count;
				if (!avgData && !avgRatingEl.innerHTML.trim()) {
					avgRatingEl.innerHTML = 'Note moyenne : ' + renderAvgStars(null, cntData);
				}
				renderUserStars();
			})();

		})();
	</script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>


</body>

</html>