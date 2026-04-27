<?php
/**
 * gallery.php — admin gallery showing all saved photos,
 * sorted by latest, paginated 9 per page.
 */

$dir        = __DIR__ . '/storage/photos/';
$perPage    = 9;
$page       = max(1, (int)($_GET['page'] ?? 1));

/* ── Collect all .jpg files sorted by mtime descending ── */
$files = [];
if (is_dir($dir)) {
    foreach (glob($dir . '*.jpg') as $f) {
        $files[] = ['path' => $f, 'mtime' => filemtime($f), 'id' => basename($f, '.jpg')];
    }
}
usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);

$total      = count($files);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;
$pageFiles  = array_slice($files, $offset, $perPage);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tommy Hilfiger · Gallery</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --th-navy:       #001E62;
            --th-red:        #CE1126;
            --th-white:      #ffffff;
            --th-off-white:  #f5f4f0;
            --th-light-gray: #ebebeb;
            --th-mid-gray:   #a0a0a0;
            --th-dark-gray:  #3a3a3a;
            --shadow-card:   0 2px 12px rgba(0,0,0,0.10);
        }

        html, body {
            min-height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--th-off-white);
            color: #1a1a1a;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header — same as index ── */
        .start-header {
            width: 100%;
            padding: 32px 24px 0;
            background: #fff;
        }
        .start-header-img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* ── Gallery title bar ── */
        .gallery-title-bar {
            background: #fff;
            padding: 16px 24px 12px;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-bottom: 1px solid var(--th-light-gray);
        }
        .gallery-title {
            font-family: 'Montserrat', sans-serif;
            font-size: clamp(.95rem, 3vw, 1.2rem);
            font-weight: 900;
            letter-spacing: 3px;
            color: var(--th-dark-gray);
            text-transform: uppercase;
        }
        .gallery-count {
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--th-mid-gray);
            text-transform: uppercase;
        }

        /* ── Main content ── */
        .gallery-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 20px 48px;
        }

        /* ── Empty state ── */
        .gallery-empty {
            text-align: center;
            padding: 80px 24px;
            color: var(--th-mid-gray);
        }
        .gallery-empty p {
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* ── Grid ── */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
        @media (max-width: 700px) {
            .gallery-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 420px) {
            .gallery-grid { grid-template-columns: 1fr; }
        }

        .gallery-card {
            background: #fff;
            border-radius: 2px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            transition: transform .2s, box-shadow .2s;
        }
        .gallery-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,.14);
        }
        .gallery-card-img-wrap {
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            background: var(--th-light-gray);
        }
        .gallery-card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            cursor: pointer;
        }
        .gallery-card-body {
            padding: 9px 11px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border-top: 1px solid var(--th-light-gray);
        }
        .card-date {
            font-size: .7rem;
            color: var(--th-mid-gray);
            font-weight: 500;
            white-space: nowrap;
        }
        .card-btn {
            padding: 4px 12px;
            font-family: 'Montserrat', sans-serif;
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 2px;
            border: 1.5px solid #ccc;
            background: transparent;
            color: var(--th-dark-gray);
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
        }
        .card-btn:hover {
            background: var(--th-dark-gray);
            border-color: var(--th-dark-gray);
            color: #fff;
        }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 36px;
            flex-wrap: wrap;
        }
        .pg-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 2px;
            border: 1.5px solid #ddd;
            background: #fff;
            color: var(--th-dark-gray);
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-decoration: none;
            transition: background .15s, border-color .15s, color .15s;
        }
        .pg-btn:hover {
            background: var(--th-dark-gray);
            color: #fff;
            border-color: var(--th-dark-gray);
        }
        .pg-btn.active {
            background: var(--th-dark-gray);
            color: #fff;
            border-color: var(--th-dark-gray);
            pointer-events: none;
        }
        .pg-btn.disabled {
            opacity: .3;
            pointer-events: none;
        }
        .pg-info {
            font-family: 'Montserrat', sans-serif;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--th-mid-gray);
            text-transform: uppercase;
            margin: 0 4px;
        }

        /* ── Lightbox ── */
        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.88);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .lightbox.open { display: flex; }
        .lightbox-inner {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
        }
        .lightbox-inner img {
            max-width: 100%;
            max-height: 88vh;
            object-fit: contain;
            display: block;
            border-radius: 2px;
        }
        .lightbox-close {
            position: absolute;
            top: -14px;
            right: -14px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #fff;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--th-dark-gray);
            font-weight: 900;
        }

        /* ── Footer — same as index ── */
        .start-footer-bar {
            display: flex;
            justify-content: center;
            padding: 0 24px 28px;
            background: #fff;
            border-top: 1px solid var(--th-light-gray);
            margin-top: 24px;
        }
        .start-footer-logo {
            height: 20px;
            width: auto;
            display: block;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<header class="start-header">
    <img class="start-header-img" src="asset/header.webp" alt="Hosted by Hilfiger — Summer Gazette 2026">
</header>

<div class="gallery-title-bar">
    <span class="gallery-title">Gallery</span>
    <span class="gallery-count"><?= $total ?> photo<?= $total !== 1 ? 's' : '' ?></span>
</div>

<main class="gallery-main">

    <?php if (empty($pageFiles)): ?>
    <div class="gallery-empty">
        <p>No photos yet</p>
    </div>
    <?php else: ?>

    <div class="gallery-grid">
        <?php foreach ($pageFiles as $f):
            $viewUrl = $scheme . '://' . $host . '/download.php?id=' . $f['id'] . '&view=1';
            $dlUrl   = $scheme . '://' . $host . '/download.php?id=' . $f['id'] . '&dl=1';
            $date    = date('M d, Y · H:i', $f['mtime']);
        ?>
        <div class="gallery-card">
            <div class="gallery-card-img-wrap">
                <img src="<?= htmlspecialchars($viewUrl, ENT_QUOTES) ?>"
                     alt="Photo from <?= htmlspecialchars($date, ENT_QUOTES) ?>"
                     loading="lazy"
                     data-full="<?= htmlspecialchars($viewUrl, ENT_QUOTES) ?>"
                     class="gallery-img">
            </div>
            <div class="gallery-card-body">
                <span class="card-date"><?= htmlspecialchars($date) ?></span>
                <div class="card-actions">
                    <a href="<?= htmlspecialchars($dlUrl, ENT_QUOTES) ?>" class="card-btn">DOWNLOAD</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <a href="?page=<?= $page - 1 ?>"
           class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>"
           aria-label="Previous">&#8592;</a>

        <?php
        // Show at most 7 page buttons with ellipsis
        $start = max(1, $page - 3);
        $end   = min($totalPages, $page + 3);
        if ($start > 1): ?>
            <a href="?page=1" class="pg-btn">1</a>
            <?php if ($start > 2): ?><span class="pg-info">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="?page=<?= $p ?>" class="pg-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="pg-info">…</span><?php endif; ?>
            <a href="?page=<?= $totalPages ?>" class="pg-btn"><?= $totalPages ?></a>
        <?php endif; ?>

        <a href="?page=<?= $page + 1 ?>"
           class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
           aria-label="Next">&#8594;</a>

        <span class="pg-info">Page <?= $page ?> of <?= $totalPages ?></span>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

</main>

<footer class="start-footer-bar">
    <img class="start-footer-logo" src="asset/logo.png" alt="Tommy Hilfiger">
</footer>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
    <div class="lightbox-inner">
        <button class="lightbox-close" id="lb-close" aria-label="Close">&#x2715;</button>
        <img id="lb-img" src="" alt="Full size photo">
    </div>
</div>

<script>
    const lightbox = document.getElementById('lightbox');
    const lbImg    = document.getElementById('lb-img');

    document.querySelectorAll('.gallery-img').forEach(img => {
        img.style.cursor = 'pointer';
        img.addEventListener('click', () => {
            lbImg.src = img.dataset.full;
            lightbox.classList.add('open');
        });
    });

    document.getElementById('lb-close').addEventListener('click', closeLb);
    lightbox.addEventListener('click', e => { if (e.target === lightbox) closeLb(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLb(); });

    function closeLb() {
        lightbox.classList.remove('open');
        lbImg.src = '';
    }
</script>

</body>
</html>
