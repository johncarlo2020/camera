<?php
/**
 * download.php — mobile-friendly photo download page.
 *
 * ?id=<32 hex chars>          → show the download page
 * ?id=<32 hex chars>&view=1   → serve the image inline (used by <img src>)
 * ?id=<32 hex chars>&dl=1     → force-download the JPEG
 */

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
    <meta name="theme-color" content="#001E62">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Tommy Hilfiger · Your Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100svh;
            background: #f5f4f0;
            font-family: 'Montserrat', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            -webkit-font-smoothing: antialiased;
        }

        /* ── TH flag stripe ── */
        .flag-bar { width: 100%; height: 6px; display: flex; flex-shrink: 0; }
        .flag-bar div { flex: 1; }
        .f-navy  { background: #001E62; }
        .f-white { background: #fff; }
        .f-red   { background: #CE1126; }

        /* ── Header ── */
        .header {
            width: 100%;
            padding: 18px 20px 16px;
            text-align: center;
            background: #fff;
            border-bottom: 1px solid #e8e8e8;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: .72rem;
            font-weight: 800;
            color: #001E62;
            letter-spacing: 3px;
        }

        .flag-icon {
            display: inline-block;
            width: 22px;
            height: 14px;
            background: linear-gradient(to right, #fff 50%, #CE1126 50%);
            border: 2px solid #001E62;
            border-radius: 1px;
            flex-shrink: 0;
            vertical-align: middle;
        }

        .header-sub {
            margin-top: 5px;
            font-size: .52rem;
            letter-spacing: 3.5px;
            color: #bbb;
        }

        /* ── Photo ── */
        .photo-wrap {
            width: 100%;
            max-width: 480px;
            padding: 24px 16px 0;
        }

        .photo-wrap img {
            width: 100%;
            display: block;
            border-radius: 2px;
            box-shadow: 0 8px 36px rgba(0,0,0,.20);
        }

        /* ── Actions ── */
        .actions {
            width: 100%;
            max-width: 480px;
            padding: 24px 16px 48px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-save {
            display: block;
            width: 100%;
            padding: 17px;
            background: #001E62;
            color: #fff;
            border: none;
            border-radius: 0;
            font-family: 'Montserrat', sans-serif;
            font-size: .85rem;
            font-weight: 800;
            letter-spacing: 4px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-save:active { background: #000d38; }

        .note {
            text-align: center;
            font-size: .58rem;
            color: #bbb;
            letter-spacing: 1.5px;
            line-height: 1.7;
        }

        /* ── Footer ── */
        .footer {
            margin-top: auto;
            padding: 20px;
            font-size: .5rem;
            letter-spacing: 2px;
            color: #ccc;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="flag-bar">
        <div class="f-navy"></div>
        <div class="f-white"></div>
        <div class="f-red"></div>
    </div>

    <div class="header">
        <div class="logo">
            TOMMY <span class="flag-icon"></span> HILFIGER
        </div>
        <p class="header-sub">PHOTO SHOOT</p>
    </div>

    <div class="photo-wrap">
        <img src="<?= $viewSrc ?>" alt="Your Tommy Hilfiger Photo">
    </div>

    <div class="actions">
        <a class="btn-save" href="<?= $dlHref ?>">SAVE PHOTO</a>
        <p class="note">TAP SAVE PHOTO TO DOWNLOAD TO YOUR DEVICE</p>
    </div>

    <p class="footer">TOMMY HILFIGER &nbsp;·&nbsp; PHOTO SHOOT</p>

</body>
</html>
