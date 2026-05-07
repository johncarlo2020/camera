<?php
/**
 * download.php — mobile-friendly photo download page.
 *
 * ?id=<32 hex chars>          → show the download page
 * ?id=<32 hex chars>&view=1   → serve the image inline (used by <img src>)
 * ?id=<32 hex chars>&dl=1     → force-download the JPEG
 */

/* ── Security headers ─────────────────────────────────── */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ── Per-IP rate limit (download enumeration / scraping) ─ */
(function () {
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $hash   = hash('sha256', $ip);
    $dir    = sys_get_temp_dir() . '/cam_dl_rl/';
    $file   = $dir . $hash . '.json';
    $now    = time();
    $window = 60;
    $max    = 30; // 30 download page hits per IP per minute

    if (!is_dir($dir)) { mkdir($dir, 0700, true); }

    // Probabilistic stale-file cleanup
    if (random_int(1, 100) === 1) {
        foreach (glob($dir . '*.json') as $f) {
            if (filemtime($f) < $now - $window * 2) { @unlink($f); }
        }
    }

    $fp = @fopen($file, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) fclose($fp);
        return; // fail-open on FS errors
    }

    $d = json_decode(fread($fp, 256), true) ?? ['c' => 0, 'ts' => $now];
    if ($now - $d['ts'] > $window) { $d = ['c' => 0, 'ts' => $now]; }
    $d['c']++;

    if ($d['c'] > $max) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        header('Retry-After: ' . ($window - ($now - $d['ts'])));
        header('Content-Type: text/plain');
        exit('Too many requests. Please slow down.');
    }

    fseek($fp, 0); ftruncate($fp, 0);
    fwrite($fp, json_encode($d));
    flock($fp, LOCK_UN);
    fclose($fp);
})();

/* ── Validate ID ──────────────────────────────────────── */
$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!preg_match('/^[0-9a-f]{32}$/', $id)) {
    http_response_code(400);
    exit('Invalid request.');
}

$path = __DIR__ . '/storage/photos/' . $id . '.jpg';
if (!is_file($path)) {
    http_response_code(404);
    exit('Photo not found or expired.');
}

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$base    = $scheme . '://' . $host . '/download.php?id=' . htmlspecialchars($id, ENT_QUOTES);

/* ── Serve image inline ───────────────────────────────── */
if (isset($_GET['view'])) {
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

/* ── Force download ───────────────────────────────────── */
if (isset($_GET['dl'])) {
    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="tommy-hilfiger-shoot.jpg"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ── Mobile download page ─────────────────────────────── */
$viewSrc = $base . '&view=1';
$dlHref  = $base . '&dl=1';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Tommy Hilfiger · Your Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800;900&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100svh;
            background: #fff;
            font-family: 'Montserrat', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            -webkit-font-smoothing: antialiased;
            color: #1a1a1a;
        }

        /* ── Site header ── */
        .site-header {
            width: 100%;
            max-width: 480px;
            padding: 24px 20px 16px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .header-branding {
            flex: 1;
            min-width: 0;
        }

        .header-branding img {
            width: 100%;
            max-width: 220px;
            display: block;
        }

        /* ── Action buttons ── */
        .header-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            background: #fff;
            color: #1a1a1a;
            border: 1.5px solid #1a1a1a;
            font-family: 'Montserrat', sans-serif;
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-action:active,
        .btn-action:hover { background: #1a1a1a; color: #fff; }
        .btn-action:hover svg,
        .btn-action:active svg { stroke: #fff; }

        .btn-action svg {
            width: 13px;
            height: 13px;
            stroke: #1a1a1a;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
            transition: stroke .15s;
        }

        /* ── Photo ── */
        .photo-wrap {
            width: 100%;
            max-width: 480px;
            padding: 4px 20px 0;
        }

        .photo-wrap img {
            width: 100%;
            display: block;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
        }

        /* ── Footer ── */
        .site-footer {
            margin-top: auto;
            padding: 28px 20px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-logo { height: 20px; display: block; }
    </style>
</head>
<body>

    <div class="site-header">
        <div class="header-branding">
            <!-- <img src="asset/header.webp" alt="Hosted by Hilfiger — Summer Gazette 2026"> -->
        </div>
        <div class="header-actions">
            <a class="btn-action" href="<?= $dlHref ?>">
                <svg viewBox="0 0 24 24"><path d="M12 3v13M5 16l7 7 7-7M3 21h18"/></svg>
                DOWNLOAD
            </a>
            <button class="btn-action" id="btn-share">
                <svg viewBox="0 0 24 24"><path d="M12 21V8M5 15l7-7 7 7M3 3h18"/></svg>
                SHARE
            </button>
        </div>
    </div>

    <div class="photo-wrap">
        <img src="<?= $viewSrc ?>" alt="Your Tommy Hilfiger Photo">
    </div>

    <footer class="site-footer">
        <!-- <img src="asset/logo.png" alt="Tommy Hilfiger" class="footer-logo"> -->
    </footer>

    <script>
        document.getElementById('btn-share').addEventListener('click', async function () {
            const url = <?= json_encode($base) ?>;
            try {
                if (navigator.share) {
                    await navigator.share({ title: 'Tommy Hilfiger — Summer Gazette 2026', url: url });
                } else if (navigator.clipboard) {
                    await navigator.clipboard.writeText(url);
                    this.textContent = 'COPIED!';
                    setTimeout(() => { this.innerHTML = '<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0"><path d="M12 21V8M5 15l7-7 7 7M3 3h18"/></svg> SHARE'; }, 2000);
                }
            } catch (e) {}
        });
    </script>

</body>
</html>
