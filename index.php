<?php
require __DIR__ . '/init.php';
require_login();


// search and filter params
$q = trim($_GET['q'] ?? '');
$filterChar = trim($_GET['character'] ?? '');

// pagination params
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12; // change this to show more/less per page
$offset = ($page - 1) * $perPage;

// get favorites filter from query
$favFilter = isset($_GET['favorites']) && ($_GET['favorites'] === '1' || $_GET['favorites'] === 'true');

// build WHERE conditions and params
$sqlBase = 'FROM videos v
            JOIN users u ON v.user_id = u.id
            LEFT JOIN favorites f ON f.video_id = v.id AND f.user_id = :current_user';
$conds = [];
$params = [':current_user' => $_SESSION['user_id']];
if ($q !== '') {
	$conds[] = 'v.title LIKE :q';
	$params[':q'] = "%$q%";
}
if ($filterChar !== '') {
	$conds[] = 'v.characters LIKE :character';
	$params[':character'] = "%$filterChar%";
}
if ($favFilter) {
	// only show videos that have a favorite row for this user
	$conds[] = 'f.id IS NOT NULL';
}
$where = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';

try {
	// total count
	$countSql = "SELECT COUNT(*) AS cnt $sqlBase $where";
	$stmt = $pdo->prepare($countSql);
	foreach ($params as $k => $v) $stmt->bindValue($k, $v);
	$stmt->execute();
	$total = (int)$stmt->fetchColumn();

	// fetch page rows with LIMIT/OFFSET (bind as integers)
	$selectSql = "
		SELECT v.*, u.username, (f.id IS NOT NULL) AS is_fav,
		(SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id) AS comment_count
		$sqlBase
		$where
		ORDER BY v.file_date DESC
		LIMIT :limit OFFSET :offset
	";
	$stmt = $pdo->prepare($selectSql);
	foreach ($params as $k => $v) $stmt->bindValue($k, $v);
	$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
	$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
	$stmt->execute();
	$videos = $stmt->fetchAll();
} catch (Exception $e) {
	// In case of DB error, show empty list but log error
	error_log('Index pagination error: ' . $e->getMessage());
	$total = 0;
	$videos = [];
}

// helper to preserve query params while changing page
function build_query(array $overrides = [])
{
	$base = [];
	if (isset($_GET['q'])) $base['q'] = $_GET['q'];
	if (isset($_GET['character'])) $base['character'] = $_GET['character'];
	$merged = array_merge($base, $overrides);
	return http_build_query($merged);
}

// pagination calculations
$totalPages = (int)ceil($total / $perPage);
$startItem = $total === 0 ? 0 : $offset + 1;
$endItem = min($offset + count($videos), $total);
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
			<a class="navbar-brand text-dark fs-1" href="/index.php">SPÖÖK <span class="bg-dark rounded-4 text-light p-2 px-3">TUBE</span></a>
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
					Inserer un disque
				</a>
			</div>
		</div>
		<div class="row mx-2">
			<div class="col-4 m-auto">
				<h2 class="text-uppercase"><i class="bi bi-camera-video-fill"></i> videos</h2>
			</div>
			<div class="col-2 text-end m-auto">
				<button type="button" id="onlyFavoritesSwitch" class="btn btn-outline-dark rounded-6 <?= $favFilter ? 'active' : '' ?>" data-bs-toggle="button"><i class="bi bi-heart-fill"></i> Favoris</button>
			</div>
			<div class="col m-auto">
				<form method="get" class="input-group shadow-sm rounded-6">
					<input type="text" class="form-control rounded-start-6" placeholder="Chercher par titre" name="q" value="<?= h($q) ?>">
					<input type="text" class="form-control" placeholder="Chercher par gens" name="character" value="<?= h($filterChar) ?>">
					<?php if ($q !== '' || $filterChar !== ''): ?>
						<button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
						<a href="index.php" class="btn btn-dark rounded-end-6" type="button"><i class="bi bi-x"></i></a>
					<?php else: ?>
						<button class="btn btn-dark rounded-end-6" type="submit"><i class="bi bi-search"></i></button>
					<?php endif; ?>
				</form>
			</div>
		</div>

		<div class="row">
			<?php if ($total === 0): ?>
				<h1 class="text-center text-uppercase my-5">
					No videos found
					<br class="my-5">
					<i class="bi bi-camera-video-off-fill fs-1"></i>
				</h1>
			<?php else: ?>
				<div class="row row-cols-4 g-5 mt-1 mb-4">
					<?php foreach ($videos as $v): list($avg, $cnt) = avg_rating($pdo, $v['id']); ?>
						<div class="col my-2">
							<div class="card card-white shadow-sm border-0 rounded-5">
								<?php if (!empty($v['thumbnail'])): ?>
									<img src="serve_thumb.php?f=<?= urlencode($v['thumbnail']) ?>" alt="thumb" class="thumb rounded-top-5">
								<?php else: ?>
									<img class="thumb lazy-thumb"
										data-video-src="serve_video.php?f=<?= urlencode($v['filename']) ?>"
										data-video-id="<?= (int)$v['id'] ?>"
										alt="thumb"
										src="data:image/svg+xml;charset=UTF-8,<?= rawurlencode('<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;320&quot; height=&quot;180&quot;><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;%23e9ecef&quot; /><text x=&quot;50%&quot; y=&quot;50%&quot; dominant-baseline=&quot;middle&quot; text-anchor=&quot;middle&quot; font-family=&quot;Arial&quot; font-size=&quot;14&quot; fill=&quot;%236c757d&quot;>Loading…</text></svg>') ?>" />
								<?php endif; ?>
								<div class="card-body">
									<a href="view.php?id=<?= $v['id'] ?>" class="card-title h5 text-decoration-none stretched-link"><?= h($v['title']) ?></a>
									<div class="chars"><i class="bi bi-person-standing"></i><?= h($v['characters']) ?></div>
									<div class="rating">
										<?= render_stars($avg, $cnt) ?>
										<i class="ms-2 bi bi-chat-dots-fill"></i> <?= (int)$v['comment_count'] ?>
										<?= $v['is_fav'] ? '<i class="ms-2 bi bi-heart-fill"></i>' : '' ?>
									</div>
									<div class="filedate"><i class="bi bi-calendar3"></i> <?= h(format_date_ddmmyyyy($v['file_date'])) ?></div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<!-- Pagination controls -->
				<?php if ($totalPages > 1): ?>
					<nav aria-label="Page navigation" class="mt-4">
						<ul class="pagination justify-content-center pagination-lg">
							<!-- First -->
							<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
								<a class="page-link <?= $page >= $totalPages ? '' : 'btn-outline-dark' ?>" href="?<?= build_query(['page' => 1]) ?>" aria-label="First">«</a>
							</li>

							<!-- Prev -->
							<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
								<a class="page-link <?= $page >= $totalPages ? '' : 'btn-outline-dark' ?>" href="?<?= build_query(['page' => max(1, $page - 1)]) ?>" aria-label="Previous">‹</a>
							</li>

							<?php
							// show nearby pages: window of pages around current
							$window = 3;
							$start = max(1, $page - $window);
							$end = min($totalPages, $page + $window);
							if ($start > 1) {
								echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
							}
							for ($p = $start; $p <= $end; $p++): ?>
								<li class="page-item <?= $p == $page ? 'active' : '' ?>">
									<a class="page-link" href="?<?= build_query(['page' => $p]) ?>"><?= $p ?></a>
								</li>
							<?php endfor;
							if ($end < $totalPages) {
								echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
							}
							?>

							<!-- Next -->
							<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
								<a class="page-link <?= $page >= $totalPages ? '' : 'btn-outline-dark' ?>" href="?<?= build_query(['page' => min($totalPages, $page + 1)]) ?>" aria-label="Next">›</a>
							</li>

							<!-- Last -->
							<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
								<a class="page-link <?= $page >= $totalPages ? '' : 'btn-outline-dark' ?>" href="?<?= build_query(['page' => $totalPages]) ?>" aria-label="Last">»</a>
							</li>
						</ul>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>

	<script>
		document.getElementById('onlyFavoritesSwitch').addEventListener('click', function() {
			const checked = this.classList.contains('active') ? '1' : '0';
			console.log('fav switch', checked);
			// rebuild query string preserving q and character
			const params = new URLSearchParams(window.location.search);
			if (checked === '1') params.set('favorites', '1');
			else params.delete('favorites');
			params.delete('page'); // go back to page 1
			window.location.search = params.toString();
		});
	</script>

	<script>
		(() => {
			const maxConcurrent = 2; // how many thumbnails to generate in parallel
			const captureTimeSec = 0.0; // time (sec) into the video to capture (0.0 = very start)
			const uploadGenerated = true; // set false to only generate client-side (no upload)
			const thumbMime = 'image/webp'; // 'image/webp' or 'image/jpeg'
			const thumbQuality = 0.5; // 0..1

			const queue = [];
			let running = 0;

			// find all placeholder thumbs on page
			const placeholders = Array.from(document.querySelectorAll('img.lazy-thumb'));

			if (!placeholders.length) return;

			// IntersectionObserver to start generation when visible
			const io = ('IntersectionObserver' in window) ? new IntersectionObserver(onIntersect, {
				rootMargin: '300px'
			}) : null;
			placeholders.forEach(el => {
				if (io) io.observe(el);
				else queue.push(el); // fallback: add all immediately
			});

			function onIntersect(entries, obs) {
				entries.forEach(e => {
					if (e.isIntersecting) {
						obs.unobserve(e.target);
						enqueue(e.target);
					}
				});
			}

			function enqueue(imgEl) {
				queue.push(imgEl);
				processQueue();
			}

			function processQueue() {
				if (running >= maxConcurrent || queue.length === 0) return;
				const el = queue.shift();
				running++;
				generateThumbFor(el).finally(() => {
					running--;
					processQueue();
				});
			}

			async function generateThumbFor(imgEl) {
				const videoSrc = imgEl.dataset.videoSrc;
				const videoId = imgEl.dataset.videoId ? parseInt(imgEl.dataset.videoId, 10) : null;
				if (!videoSrc) return;

				// create offscreen video element
				const video = document.createElement('video');
				video.preload = 'metadata';
				video.muted = true;
				video.playsInline = true;
				video.crossOrigin = 'anonymous'; // may be needed if video served with CORS
				let objectUrl = null;

				// If the src is same-origin (it is), we can assign the URL directly
				video.src = videoSrc;

				try {
					// wait for enough data to draw first frame
					await waitForLoadedData(video);

					// choose capture time (bounded by duration)
					let t = captureTimeSec;
					if (isFinite(video.duration) && video.duration > 0) {
						// if clip is shorter than captureTimeSec, use 0
						if (video.duration < t) t = 0;
					} else {
						t = 0;
					}

					// seek to time
					await seekVideo(video, t);

					// draw frame to canvas
					const canvas = document.createElement('canvas');
					const w = video.videoWidth;
					const h = video.videoHeight;
					if (!w || !h) throw new Error('Video has no dimensions');
					// target width (you can tune), keep aspect ratio
					const maxW = 640;
					let targetW = w;
					let targetH = h;
					if (w > maxW) {
						const ratio = maxW / w;
						targetW = Math.round(w * ratio);
						targetH = Math.round(h * ratio);
					}
					canvas.width = targetW;
					canvas.height = targetH;
					const ctx = canvas.getContext('2d');
					ctx.drawImage(video, 0, 0, targetW, targetH);

					// convert to blob (WebP)
					const blob = await canvasToBlob(canvas, thumbMime, thumbQuality);
					if (!blob) throw new Error('Failed to create thumbnail blob');

					// set placeholder image to generated thumbnail
					const dataUrl = await blobToDataURL(blob);
					imgEl.src = dataUrl;

					// optionally upload to server to persist thumb and update DB
					if (uploadGenerated && videoId) {
						// send as multipart/form-data with thumbnail blob
						try {
							await uploadThumbnail(videoId, blob);
							// on success, the server will have stored a persistent thumbnail and updated DB
							// To reflect that for caching/next loads, we could replace src with serve_thumb.php?f=... using server response
							// uploadThumbnail will return maybe the filename; if so, replace src to serve_thumb.php?f=filename
						} catch (err) {
							console.warn('Thumbnail upload failed', err);
							// ignoring; client-side thumb remains visible
						}
					}

				} catch (err) {
					console.warn('Thumb generation failed for', imgEl, err);
					// leave placeholder as-is or set a "no-thumb" image
				} finally {
					// cleanup
					try {
						video.pause();
						video.removeAttribute('src');
						video.load && video.load();
					} catch (e) {}
					if (objectUrl) try {
						URL.revokeObjectURL(objectUrl);
					} catch (e) {}
				}
			}

			// helpers
			function waitForLoadedData(video) {
				return new Promise((resolve, reject) => {
					// timeout in case the browser stalls
					const to = setTimeout(() => {
						cleanup();
						reject(new Error('Timeout while loading video metadata'));
					}, 10000);

					function onLoadedData() {
						cleanup();
						resolve();
					}

					function onError(e) {
						cleanup();
						reject(new Error('Video load error'));
					}

					function cleanup() {
						clearTimeout(to);
						video.removeEventListener('loadeddata', onLoadedData);
						video.removeEventListener('error', onError);
					}
					video.addEventListener('loadeddata', onLoadedData);
					video.addEventListener('error', onError);
					// Some browsers won't fire loadeddata until the video starts downloading; setting src earlier triggers it.
					// If needed, call video.load() - but assigning src is usually sufficient.
				});
			}

			function seekVideo(video, seconds) {
				return new Promise((resolve, reject) => {
					// If already at or past desired time, resolve
					try {
						if (Math.abs((video.currentTime || 0) - seconds) < 0.01) return resolve();
					} catch (e) {}
					const to = setTimeout(() => {
						cleanup();
						reject(new Error('Timeout during seek'));
					}, 10000);

					function onSeeked() {
						cleanup();
						resolve();
					}

					function onError() {
						cleanup();
						reject(new Error('Seek error'));
					}

					function cleanup() {
						clearTimeout(to);
						video.removeEventListener('seeked', onSeeked);
						video.removeEventListener('error', onError);
					}
					video.addEventListener('seeked', onSeeked);
					video.addEventListener('error', onError);
					try {
						video.currentTime = Math.max(0, seconds);
					} catch (e) {
						cleanup();
						reject(e);
					}
				});
			}

			function canvasToBlob(canvas, mime, quality) {
				return new Promise((resolve) => {
					if (canvas.toBlob) {
						canvas.toBlob(resolve, mime, quality);
					} else {
						// fallback
						const data = canvas.toDataURL(mime, quality);
						const blob = dataURLtoBlob(data);
						resolve(blob);
					}
				});
			}

			function blobToDataURL(blob) {
				return new Promise((resolve, reject) => {
					const r = new FileReader();
					r.onload = () => resolve(r.result);
					r.onerror = reject;
					r.readAsDataURL(blob);
				});
			}

			function dataURLtoBlob(dataurl) {
				const arr = dataurl.split(',');
				const mime = arr[0].match(/:(.*?);/)[1];
				const bstr = atob(arr[1]);
				let n = bstr.length;
				const u8arr = new Uint8Array(n);
				while (n--) u8arr[n] = bstr.charCodeAt(n);
				return new Blob([u8arr], {
					type: mime
				});
			}

			function uploadThumbnail(videoId, blob) {
				// POST /save_thumb.php with video_id and thumbnail file (multipart)
				const fd = new FormData();
				fd.append('video_id', String(videoId));
				fd.append('thumbnail', blob, 'thumb.webp');
				return fetch('save_thumb.php', {
					method: 'POST',
					body: fd,
					credentials: 'same-origin'
				}).then(r => {
					if (!r.ok) throw new Error('Upload failed: ' + r.status);
					return r.json();
				}).then(json => {
					if (!json.success) throw new Error(json.error || 'unknown');
					// if server returns filename, we can switch to the persistent URL:
					if (json.filename) {
						// replace current img src to the server-served thumb (cache-busted)
						// find the <img> with data-video-id
						const img = document.querySelector('img.lazy-thumb[data-video-id="' + videoId + '"]');
						if (img) {
							img.src = 'serve_thumb.php?f=' + encodeURIComponent(json.filename) + '&_=' + Date.now();
						}
					}
					return json;
				});
			}

		})(); // IIFE
	</script>
</body>

</html>