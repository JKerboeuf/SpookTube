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

$stmt = $pdo->prepare('SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.video_id = ? ORDER BY c.created_at DESC');
$stmt->execute([$v['id']]);
$comments = $stmt->fetchAll();
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
			<a class="navbar-brand text-dark fs-1" href="/index.php">SPÖÖK <span class="bg-dark rounded-4 text-light p-2 px-3">TUBE</span></a>
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
						<video controls id="player" class="flex-grow-1" style="width:100%; height:100%; object-fit:contain; display:block;">
							<source src="serve_video.php?f=<?= urlencode($v['filename']) ?>">
							Your browser does not support HTML5 video.
						</video>
					</div>
				</div>

				<div class="col h-100 d-flex flex-column">
					<div class="bg-white shadow rounded-6 mt-4 p-2">
						<div class="mx-2 d-flex justify-content-between">
							<div>
								<h2 class="mb-2 d-inline"><?= h($v['title']) ?></h2>
								<div class="ps-2 d-inline" id="avgRating" data-avg="<?= htmlspecialchars($avg ?? '', ENT_QUOTES) ?>" data-count="<?= intval($cnt) ?>">
									<?= render_stars($avg, $cnt) ?>
								</div>
							</div>
							<div class="text-end">
								<div class="d-inline-flex flex-row justify-content-center align-items-center bg-black rounded-6 mx-auto text-white p-1 px-2">
									<div id="userRating" role="radiogroup" class="d-inline-block text-white"></div>
									<div id="rateResult" class="ms-2"></div>
								</div>
								<?php if ($isFav): ?>
									<button type="button" id="onlyFavoritesSwitch" data-video-id="<?= (int)$v['id'] ?>" aria-pressed="true" class="btn btn-outline-dark active rounded-6"><i class="bi bi-heart-fill"></i> Favori</button>
								<?php else: ?>
									<button type="button" id="onlyFavoritesSwitch" data-video-id="<?= (int)$v['id'] ?>" aria-pressed="false" class="btn btn-outline-dark rounded-6"><i class="bi bi-heart"></i> Ajouter aux favoris</button>
								<?php endif; ?>
								<?php if ($v['user_id'] == $_SESSION['user_id'] || $_SESSION['user_id'] == 1): ?>
									<a class="btn btn-dark rounded-6" href="edit.php?id=<?= $v['id'] ?>"><i class="bi bi-pencil-fill"></i> Modifier</a>
									<form method="post" action="delete.php" onsubmit="return confirm('Supprimer la vidéo? La suppression sera définitive.');" style="display:inline">
										<input type="hidden" name="id" value="<?= $v['id'] ?>">
										<button class="btn btn-dark rounded-6" type="submit"><i class="bi bi-trash3-fill"></i> Supprimer</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
						<div class="mx-2 mb-2">
							<p>Posté par <span class="fw-bold"><?= h($v['username']) ?></span>, subi le <span class="fw-bold"><?= h(format_date_ddmmyyyy($v['file_date'])) ?></span>, avec <span class="fw-bold"><?= h($v['characters']) ?></span></p>
							<?= nl2br(h($v['description'])) ?>
						</div>
					</div>
					<div class="p-2 mt-3">
						<h3 class="mb-2 text-uppercase">Commentaires</h3>
						<div class="mb-4 mx-1 p-1 rounded-6 row shadow bg-white">
							<div class="col-2 my-auto">
								<input type="checkbox" class="btn-check" id="includeTimecode" autocomplete="off">
								<label class="btn btn-outline-dark rounded-6" for="includeTimecode"><i class="bi bi-stopwatch-fill"></i> Ajouter timecode</label><br>
							</div>
							<div class="col-9 my-auto">
								<input type="text" id="commentText" class="form-control border-0" maxlength="1000" placeholder="Ajouter un commentaire..."></textarea>
							</div>
							<div class="col-1 my-auto">
								<button id="submitCommentBtn" class="btn btn-dark btn-lg rounded-6"><i class="bi bi-send-fill"></i></button>
								<span id="commentResult" class="ms-2 text-muted" aria-live="polite"></span>
							</div>
						</div>
						<ul id="commentsList" class="mx-3 list-group">
							<?php foreach ($comments as $c): ?>
								<li class="list-group-item" data-comment-id="<?= (int)$c['id'] ?>">
									<div class="d-flex justify-content-between">
										<div><strong><?= h($c['username']) ?></strong> <small class="text-muted"><?= h(format_timecode($c['created_at'])) ?></small></div>
										<button class="btn btn-sm btn-dark rounded-6"><i class="bi bi-trash3-fill"></i></button>
									</div>
									<div class="mt-2 comment-content">
										<?php if ($c['timecode'] !== null): ?>
											<button class="timecode-link btn btn-sm btn-dark rounded-6" data-seconds="<?= (int)$c['timecode'] ?>"><i class="bi bi-play-fill"></i> <?= h(format_timecode($c['timecode'] ?? 0)) ?></button>
										<?php endif; ?>
										<div class="d-inline"><?= nl2br(h($c['content'])) ?></div>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
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
			const btn = document.getElementById('submitCommentBtn');
			const textarea = document.getElementById('commentText');
			const includeBox = document.getElementById('includeTimecode');
			const resultEl = document.getElementById('commentResult');
			const commentsList = document.getElementById('commentsList');
			const video = document.getElementById('player'); // ensure your <video id="player">

			// helper to format seconds to m:ss or h:mm:ss for display (client-side)
			function fmtTimecode(s) {
				s = Math.max(0, Math.floor(s || 0));
				const h = Math.floor(s / 3600);
				const m = Math.floor((s % 3600) / 60);
				const sec = s % 60;
				if (h > 0) return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
				return `${String(m).padStart(1,'0')}:${String(sec).padStart(2,'0')}`;
			}

			// post comment
			btn.addEventListener('click', () => {
				const text = textarea.value.trim();
				if (!text) {
					resultEl.textContent = 'Please write a comment.';
					return;
				}
				btn.disabled = true;
				resultEl.textContent = 'Posting…';

				// optionally include current timecode (seconds)
				let timecode = null;
				if (includeBox.checked && video) {
					try {
						const t = Math.floor(video.currentTime || 0);
						if (!Number.isNaN(t) && t >= 0) timecode = t;
					} catch (e) {
						timecode = null;
					}
				}

				fetch('post_comment.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify({
							video_id: <?= (int)$v['id'] ?>,
							content: text,
							timecode: timecode
						}),
						credentials: 'same-origin'
					}).then(r => r.json())
					.then(data => {
						if (!data || !data.success) {
							resultEl.textContent = data && data.error ? data.error : 'Failed to post comment';
						} else {
							// append returned HTML snippet to comments list (prepend so newest shows first)
							const li = document.createElement('div');
							li.innerHTML = data.html;
							// data.html contains <li>…</li>, so we append its child
							const added = li.firstElementChild;
							if (added) {
								commentsList.insertBefore(added, commentsList.firstChild);
								textarea.value = '';
								includeBox.checked = false;
								resultEl.textContent = 'Posted';
								// briefly highlight
								added.classList.add('bg-success', 'bg-opacity-10');
								setTimeout(() => added.classList.remove('bg-success', 'bg-opacity-10'), 1200);
							} else {
								resultEl.textContent = 'Posted (but could not update UI)';
							}
						}
					}).catch(err => {
						console.error(err);
						resultEl.textContent = 'Network error';
					}).finally(() => {
						btn.disabled = false;
						setTimeout(() => resultEl.textContent = '', 2000);
					});
			});

			// delegate click on timecode links to seek the video
			commentsList.addEventListener('click', function(ev) {
				const a = ev.target.closest('.timecode-link');
				if (!a) return;
				ev.preventDefault();
				const seconds = parseInt(a.dataset.seconds, 10);
				if (Number.isFinite(seconds) && video) {
					try {
						video.currentTime = seconds;
						video.scrollIntoView({
							behavior: 'smooth',
							block: 'center'
						});
						// optionally start playback:
						video.play().catch(() => {});
					} catch (e) {
						console.warn('Could not seek video', e);
					}
				}
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
				userRatingEl.innerHTML = 'Noter : '; // clear
				for (let i = 1; i <= 5; i++) {
					const span = document.createElement('span');
					span.className = 'user-star' + (i <= currentUserRating ? ' filled' : '');
					span.setAttribute('role', 'radio');
					span.setAttribute('aria-checked', i === currentUserRating ? 'true' : 'false');
					span.setAttribute('tabindex', '0');
					span.dataset.value = i;
					span.innerHTML = '<i class="text-white bi ' + (i <= currentUserRating ? 'bi-star-fill' : 'bi-star') + '"></i>';
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
							avgRatingEl.innerHTML = renderAvgStars(newAvg, newCount);
							// update user's stars
							currentUserRating = value;
							renderUserStars();
							rateResultEl.innerHTML = iconHtml('bi-check-circle-fill text-white');
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