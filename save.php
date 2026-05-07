<?php
/**
 * save.php — receives a base64 JPEG from the front-end,
 * validates it, writes it to storage/photos/, and returns
 * the public download URL.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ── Rate limiting ────────────────────────────────────── */
// Allow 10 requests per IP per 60 seconds.
define('RL_MAX_REQUESTS', 10);
define('RL_WINDOW_SECONDS', 60);
// Hard cap on concurrent request bursts across all IPs.
define('RL_GLOBAL_MAX', 100);

function rate_limit_check(): void {
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash   = hash('sha256', $ip);           // never store raw IPs on disk
    $dir      = sys_get_temp_dir() . '/cam_rl/';
    $file     = $dir . $ipHash . '.json';
    $now      = time();

    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    // Clean up stale files older than 2× the window to avoid disk bloat.
    if (random_int(1, 50) === 1) {
        foreach (glob($dir . '*.json') as $f) {
            if (filemtime($f) < $now - RL_WINDOW_SECONDS * 2) {
                @unlink($f);
            }
        }
    }

    // Global request counter guard.
    $globalFile = $dir . '_global.json';
    $fp = fopen($globalFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $g = json_decode(fread($fp, 512), true) ?? ['count' => 0, 'ts' => $now];
        if ($now - $g['ts'] > RL_WINDOW_SECONDS) {
            $g = ['count' => 0, 'ts' => $now];
        }
        $g['count']++;
        if ($g['count'] > RL_GLOBAL_MAX) {
            flock($fp, LOCK_UN);
            fclose($fp);
            http_response_code(503);
            header('Retry-After: ' . RL_WINDOW_SECONDS);
            echo json_encode(['error' => 'Server busy — try again later']);
            exit;
        }
        fseek($fp, 0);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($g));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // Per-IP counter.
    $fp = fopen($file, 'c+');
    if (!$fp) return; // fail open — do not block legitimate traffic if FS is unavailable
    if (!flock($fp, LOCK_EX)) { fclose($fp); return; }

    $data = json_decode(fread($fp, 512), true) ?? ['count' => 0, 'ts' => $now];

    if ($now - $data['ts'] > RL_WINDOW_SECONDS) {
        $data = ['count' => 0, 'ts' => $now];
    }
    $data['count']++;

    if ($data['count'] > RL_MAX_REQUESTS) {
        flock($fp, LOCK_UN);
        fclose($fp);
        $retryAfter = RL_WINDOW_SECONDS - ($now - $data['ts']);
        http_response_code(429);
        header('Retry-After: ' . max(1, $retryAfter));
        echo json_encode(['error' => 'Too many requests — slow down']);
        exit;
    }

    fseek($fp, 0);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
}

rate_limit_check();

/* ── Only accept POST ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/* ── Reject oversized requests before reading the body ── */
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
// base64 overhead ~33 %; 30 MB raw ≈ 41 MB encoded, add small margin.
if ($contentLength > 42 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Request body too large']);
    exit;
}

/* ── Parse JSON body ──────────────────────────────────── */
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input || empty($input['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing image field']);
    exit;
}

$dataURL = $input['image'];

/* ── Validate: must be a JPEG data URL ────────────────── */
if (!preg_match('/^data:image\/jpeg;base64,([A-Za-z0-9+\/=]+)$/', $dataURL, $m)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image format — expected JPEG data URL']);
    exit;
}

$bytes = base64_decode($m[1], true);
if ($bytes === false || strlen($bytes) < 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid base64 data']);
    exit;
}

/* ── 30 MB size cap ───────────────────────────────────── */
if (strlen($bytes) > 30 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Image exceeds 30 MB limit']);
    exit;
}

/* ── Ensure storage directory exists ─────────────────── */
$dir = __DIR__ . '/storage/photos/';
if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Storage directory unavailable']);
    exit;
}

/* ── Write file with a random 16-byte hex ID ──────────── */
$id       = bin2hex(random_bytes(16));
$filepath = $dir . $id . '.jpg';

if (file_put_contents($filepath, $bytes) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write image']);
    exit;
}

/* ── Return the public download URL ──────────────────── */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$url    = $scheme . '://' . $host . '/download.php?id=' . $id;

echo json_encode(['url' => $url, 'id' => $id]);
