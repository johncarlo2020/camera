<?php
/**
 * auth.php — shared authentication + page-level rate limiting.
 *
 * Include at the very top of any protected page.
 * One session covers index.php and gallery.php.
 *
 * Change the password:
 *   php -r "echo password_hash('new-pass', PASSWORD_BCRYPT, ['cost'=>12]);"
 * Paste the result into APP_PASSWORD_HASH below.
 */

/* ── Security headers ────────────────────────────────── */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ── Per-IP rate limit for page loads (botnet GET flood) ─
 * 60 hits / IP / 60 s — runs before session logic so bots
 * spend no PHP resources past this point.
 */
(function () {
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $hash   = hash('sha256', $ip);
    $dir    = sys_get_temp_dir() . '/cam_page_rl/';
    $file   = $dir . $hash . '.json';
    $now    = time();
    $window = 60;
    $max    = 60;

    if (!is_dir($dir)) { mkdir($dir, 0700, true); }

    if (random_int(1, 100) === 1) {
        foreach (glob($dir . '*.json') as $f) {
            if (filemtime($f) < $now - $window * 2) { @unlink($f); }
        }
    }

    $fp = @fopen($file, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return; }

    $d = json_decode(fread($fp, 256), true) ?? ['c' => 0, 'ts' => $now];
    if ($now - $d['ts'] > $window) { $d = ['c' => 0, 'ts' => $now]; }
    $d['c']++;

    if ($d['c'] > $max) {
        flock($fp, LOCK_UN); fclose($fp);
        http_response_code(429);
        header('Retry-After: ' . ($window - ($now - $d['ts'])));
        header('Content-Type: text/plain');
        exit('Too many requests.');
    }

    fseek($fp, 0); ftruncate($fp, 0);
    fwrite($fp, json_encode($d));
    flock($fp, LOCK_UN); fclose($fp);
})();

/* ── Password ────────────────────────────────────────── */
// Default password: gallery2026  ← CHANGE IN PRODUCTION
define('APP_PASSWORD_HASH', '$2y$12$g/zsPHWFOT2QBvX9sfb3KenigHdckFl.7J.bEYXSBxKnKChmnnrjm');

/* ── Session ─────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_name('cam_app');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/* ── Logout ──────────────────────────────────────────── */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ── Already authenticated ───────────────────────────── */
if (!empty($_SESSION['app_auth'])) {
    return;
}

/* ── Login form submission ───────────────────────────── */
$_auth_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_pass'])) {
    if (password_verify($_POST['app_pass'], APP_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['app_auth'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $_auth_error = true;
}

/* ── Render login page ───────────────────────────────── */
http_response_code(401);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tommy Hilfiger · Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;900&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #ebebeb;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .login-wrap {
            width: min(380px, 94vw);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 28px;
        }
        .login-card {
            background: #fff;
            border: 1.5px solid #d0d0d0;
            width: 100%;
            box-shadow: 0 4px 32px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .login-card-header {
            background: #3a3a3a;
            padding: 28px 32px 24px;
            text-align: center;
        }
        .login-card-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: 5px;
            color: #fff;
            text-transform: uppercase;
        }
        .login-card-header p {
            margin-top: 6px;
            font-size: .65rem;
            color: rgba(255,255,255,.50);
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .login-card-body { padding: 32px; }
        label {
            display: block;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #5a5a5a;
            margin-bottom: 8px;
        }
        input[type="password"] {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #ccc;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            color: #1a1a1a;
            background: #fafafa;
            outline: none;
            transition: border-color .15s;
            border-radius: 0;
            -webkit-appearance: none;
        }
        input[type="password"]:focus { border-color: #3a3a3a; }
        .error-msg {
            margin-top: 8px;
            font-size: .68rem;
            color: #888;
            letter-spacing: .5px;
            min-height: 1em;
        }
        button[type="submit"] {
            margin-top: 20px;
            display: block;
            width: 100%;
            padding: 14px;
            background: #3a3a3a;
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 4px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .15s;
            border-radius: 0;
        }
        button[type="submit"]:hover  { background: #1a1a1a; }
        button[type="submit"]:active { background: #555; }
        .login-footer {
            font-size: .6rem;
            color: #aaa;
            letter-spacing: 1px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-card-header">
            <h1>Tommy Hilfiger</h1>
            <p>Photo Booth &middot; Staff Access</p>
        </div>
        <div class="login-card-body">
            <form method="POST" action="" autocomplete="off" novalidate>
                <label for="app_pass">Password</label>
                <input type="password" id="app_pass" name="app_pass"
                       autofocus required maxlength="128"
                       placeholder="Enter password">
                <p class="error-msg">
                    <?php if ($_auth_error): ?>Incorrect password. Try again.<?php endif; ?>
                </p>
                <button type="submit">Enter</button>
            </form>
        </div>
    </div>
    <p class="login-footer">Tommy Hilfiger &copy; 2026</p>
</div>
</body>
</html>
<?php
exit;
