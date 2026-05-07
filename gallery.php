<?php
/**
 * gallery.php — admin gallery showing all saved photos,
 * sorted by latest, paginated 9 per page.
 */
require_once __DIR__ . '/auth.php';

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
        .btn-logout {
            font-family: 'Montserrat', sans-serif;
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            padding: 4px 12px;
            border: 1.5px solid #ccc;
            color: var(--th-mid-gray);
            background: transparent;
            cursor: pointer;
            border-radius: 2px;
            transition: background .15s, color .15s, border-color .15s;
        }
        .btn-logout:hover { background: var(--th-dark-gray); color: #fff; border-color: var(--th-dark-gray); }

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
        .card-actions {
            display: flex;
            gap: 5px;
            align-items: center;
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

        /* ── Print modal (same as index) ── */
        .qr-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9998;
            backdrop-filter: blur(4px);
            animation: fadeInModal .25s ease;
        }
        .qr-modal.hidden { display: none; }
        @keyframes fadeInModal {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .qr-card {
            background: #f0f0f0;
            width: min(340px, 92vw);
            border: 1.5px solid #888;
            box-shadow: 0 8px 32px rgba(0,0,0,.14);
            overflow: hidden;
            animation: slideUpModal .28s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .qr-card .th-flag-bar { display: none; }
        @keyframes slideUpModal {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .th-flag-bar {
            display: flex;
            width: 100%;
            height: 8px;
        }
        .th-flag-navy  { flex: 1; background: #001E62; }
        .th-flag-white { flex: 1; background: #ffffff; }
        .th-flag-red   { flex: 1; background: #CE1126; }
        .qr-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 28px 28px;
            gap: 20px;
        }
        .modal-logo {
            width: clamp(100px, 28vw, 160px);
            height: auto;
            display: block;
            margin: 0 auto 4px;
        }
        .qr-title {
            font-family: 'Montserrat', sans-serif;
            font-size: .68rem;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 4px;
        }
        .copies-counter {
            display: flex;
            align-items: center;
            border: 1.5px solid #1a1a1a;
            overflow: hidden;
        }
        .copies-btn {
            width: 48px;
            height: 48px;
            background: transparent;
            color: #1a1a1a;
            border: none;
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            transition: background .15s, color .15s;
            flex-shrink: 0;
        }
        .copies-btn:hover  { background: #1a1a1a; color: #fff; }
        .copies-btn:active { background: #333;    color: #fff; }
        #copies-value {
            min-width: 64px;
            text-align: center;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a1a;
            letter-spacing: 1px;
            padding: 0 4px;
            border-left: 1.5px solid #1a1a1a;
            border-right: 1.5px solid #1a1a1a;
            user-select: none;
        }
        .print-modal-actions {
            display: flex;
            gap: 10px;
            width: 100%;
            margin-top: 4px;
        }
        .btn-ghost-dark {
            flex: 1;
            padding: 12px;
            font-size: .72rem;
            letter-spacing: 3px;
            text-align: center;
            background: transparent;
            color: #1a1a1a;
            border: 1.5px solid #1a1a1a;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            cursor: pointer;
            transition: background .18s, color .18s;
        }
        .btn-ghost-dark:hover { background: #1a1a1a; color: #fff; }
        .btn-primary-modal {
            flex: 1;
            padding: 12px;
            font-size: .72rem;
            letter-spacing: 3px;
            text-align: center;
            background: #3a3a3a;
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            cursor: pointer;
            transition: background .18s;
        }
        .btn-primary-modal:hover { background: #1a1a1a; }

        /* ── QR modal ── */
        .qr-canvas-wrap {
            background: #fff;
            padding: 12px;
            border-radius: 2px;
        }
        .qr-url {
            font-family: 'Inter', sans-serif;
            font-size: .6rem;
            color: var(--th-mid-gray);
            text-align: center;
            word-break: break-all;
            max-width: 240px;
        }

        /* ── Print area (hidden on screen, visible on print) ── */
        .print-area { display: none; }

        @media print {
            body > *:not(.print-area) {
                visibility: hidden !important;
                display: none !important;
            }
            body { overflow: visible; background: #fff; }
            @page { size: A3 portrait; margin: 0; }
            .print-area {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                width: 100% !important;
                height: auto !important;
            }
            .print-page {
                display: block !important;
                visibility: visible !important;
                width: 297mm;
                height: 420mm;
                page-break-after: always;
                break-after: page;
                overflow: hidden;
            }
            .print-page:last-child {
                page-break-after: avoid;
                break-after: avoid;
            }
            .print-page img {
                display: block !important;
                width: 297mm !important;
                height: 420mm !important;
                visibility: visible !important;
            }
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
    <a href="?logout=1" class="btn-logout">Logout</a>
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
                    <button class="card-btn btn-qr"
                            data-url="<?= htmlspecialchars($dlUrl, ENT_QUOTES) ?>">QR</button>
                    <button class="card-btn btn-print"
                            data-url="<?= htmlspecialchars($viewUrl, ENT_QUOTES) ?>">PRINT</button>
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

<!-- Print copies modal (same as index) -->
<div id="print-modal" class="qr-modal hidden">
    <div class="qr-card">
        <div class="th-flag-bar">
            <div class="th-flag-navy"></div>
            <div class="th-flag-white"></div>
            <div class="th-flag-red"></div>
        </div>
        <div class="qr-body">
            <img class="modal-logo" src="asset/logo.png" alt="Tommy Hilfiger">
            <p class="qr-title">NUMBER OF COPIES</p>
            <div class="copies-counter">
                <button id="btn-copies-dec" class="copies-btn" aria-label="Decrease">&#8722;</button>
                <span id="copies-value">1</span>
                <button id="btn-copies-inc" class="copies-btn" aria-label="Increase">&#43;</button>
            </div>
            <div class="print-modal-actions">
                <button id="btn-print-cancel" class="btn-ghost-dark">CANCEL</button>
                <button id="btn-print-confirm" class="btn-primary-modal">PRINT</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Download modal -->
<div id="qr-modal" class="qr-modal hidden">
    <div class="qr-card">
        <div class="th-flag-bar">
            <div class="th-flag-navy"></div>
            <div class="th-flag-white"></div>
            <div class="th-flag-red"></div>
        </div>
        <div class="qr-body">
            <img class="modal-logo" src="asset/logo.png" alt="Tommy Hilfiger">
            <p class="qr-title">SCAN TO DOWNLOAD</p>
            <div class="qr-canvas-wrap">
                <div id="qr-canvas"></div>
            </div>
            <p class="qr-url" id="qr-url-text"></p>
            <button id="btn-qr-close" class="btn-primary-modal">CLOSE</button>
        </div>
    </div>
</div>

<!-- Print area (hidden on screen, visible on @media print) -->
<div id="print-area" class="print-area"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
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

    /* ── Print ── */
    let printCopies    = 1;
    let currentPrintUrl = '';

    document.querySelectorAll('.btn-print').forEach(btn => {
        btn.addEventListener('click', () => {
            currentPrintUrl = btn.dataset.url;
            printCopies = 1;
            document.getElementById('copies-value').textContent = '1';
            document.getElementById('print-modal').classList.remove('hidden');
        });
    });

    document.getElementById('btn-copies-dec').addEventListener('click', () => {
        if (printCopies > 1) document.getElementById('copies-value').textContent = --printCopies;
    });

    document.getElementById('btn-copies-inc').addEventListener('click', () => {
        if (printCopies < 99) document.getElementById('copies-value').textContent = ++printCopies;
    });

    document.getElementById('btn-print-cancel').addEventListener('click', () => {
        document.getElementById('print-modal').classList.add('hidden');
    });

    document.getElementById('btn-print-confirm').addEventListener('click', () => {
        document.getElementById('print-modal').classList.add('hidden');
        handleGalleryPrint(currentPrintUrl, printCopies);
    });

    function rotateURL180(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width  = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.translate(img.width, img.height);
                ctx.rotate(Math.PI);
                ctx.drawImage(img, 0, 0);
                resolve(canvas.toDataURL('image/jpeg', 0.95));
            };
            img.onerror = reject;
            img.src = url;
        });
    }

    /* ── QR ── */
    document.querySelectorAll('.btn-qr').forEach(btn => {
        btn.addEventListener('click', () => {
            showGalleryQR(btn.dataset.url);
        });
    });

    function showGalleryQR(url) {
        const modal   = document.getElementById('qr-modal');
        const urlText = document.getElementById('qr-url-text');
        const wrap    = document.getElementById('qr-canvas');

        urlText.textContent = url;
        modal.classList.remove('hidden');

        const qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        wrap.innerHTML = qr.createImgTag(4, 4, 'Download QR code');
        const img = wrap.querySelector('img');
        if (img) img.style.cssText = 'display:block;width:220px;height:220px;image-rendering:pixelated';

        document.getElementById('btn-qr-close').onclick = () => modal.classList.add('hidden');
    }

    async function handleGalleryPrint(url, copies) {
        let dataURL;
        try {
            dataURL = await rotateURL180(url);
        } catch (e) {
            // fallback: print without rotation if canvas fails
            dataURL = url;
        }
        const printArea = document.getElementById('print-area');
        printArea.innerHTML = '';
        for (let i = 0; i < copies; i++) {
            const page = document.createElement('div');
            page.className = 'print-page';
            const img = document.createElement('img');
            img.src = dataURL;
            img.alt = 'Photo';
            page.appendChild(img);
            printArea.appendChild(page);
        }
        const imgs = Array.from(printArea.querySelectorAll('img'));
        await Promise.all(imgs.map(img =>
            img.complete ? Promise.resolve() :
            new Promise(resolve => { img.onload = resolve; img.onerror = resolve; })
        ));
        window.print();
    }
</script>

</body>
</html>
