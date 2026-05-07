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

/* ══════════════════════════════════════════════════════════
   RATE LIMITING  — first thing, before any body is read
   ══════════════════════════════════════════════════════════
   Attackers flooded this endpoint from two IPs with 1.7–1.8 MB
   payloads until the server died. Limits are intentionally tight:
   a real photo session takes ~30 s and produces exactly 1 save.

   Per-IP  : 3 requests / 60 s
   Global  : 20 requests / 60 s  (across ALL IPs)
*/
(function () {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash  = hash('sha256', $ip);
    $dir     = sys_get_temp_dir() . '/cam_save_rl/';
    $now     = time();
    $window  = 60;

    if (!is_dir($dir)) { mkdir($dir, 0700, true); }

    /* -- Stale-file GC (1-in-20 chance) ------------------- */
    if (random_int(1, 20) === 1) {
        foreach (glob($dir . '*.json') as $f) {
            if (filemtime($f) < $now - $window * 2) { @unlink($f); }
        }
    }

    /* -- Helper: read/increment/enforce a counter file ----- */
    $check = function (string $file, int $max) use ($now, $window): void {
        $fp = @fopen($file, 'c+');
        if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return; }

        $d = json_decode(fread($fp, 256), true) ?? ['c' => 0, 'ts' => $now];
        if ($now - $d['ts'] > $window) { $d = ['c' => 0, 'ts' => $now]; }
        $d['c']++;

        if ($d['c'] > $max) {
            flock($fp, LOCK_UN); fclose($fp);
            $retry = max(1, $window - ($now - $d['ts']));
            http_response_code(429);
            header('Retry-After: ' . $retry);
            echo json_encode(['error' => 'Too many requests — try again in ' . $retry . ' seconds']);
            exit;
        }

        fseek($fp, 0); ftruncate($fp, 0);
        fwrite($fp, json_encode($d));
        flock($fp, LOCK_UN); fclose($fp);
    };

    /* -- Global cap first (cheapest file, checked first) --- */
    $check($dir . '_global.json', 20);

    /* -- Per-IP cap ---------------------------------------- */
    $check($dir . $ipHash . '.json', 3);
})();

/* ── Only accept POST ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/* ── Reject oversized bodies BEFORE reading them ─────── */
// Real A3 collage at 0.95 quality ≈ 3.5 MB base64-encoded.
// Attackers sent 1.7–1.8 MB; cap at 4 MB to be safe.
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 4 * 1024 * 1024) {
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

/* ── 4 MB decoded size cap ────────────────────────────── */
if (strlen($bytes) > 4 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Image exceeds 4 MB limit']);
    exit;
}

/* ── Verify it is actually a JPEG (magic bytes FF D8 FF) ─ */
if (strlen($bytes) < 3 || substr($bytes, 0, 3) !== "\xFF\xD8\xFF") {
    http_response_code(400);
    echo json_encode(['error' => 'Uploaded data is not a valid JPEG']);
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
