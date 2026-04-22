<?php
/**
 * save.php — receives a base64 JPEG from the front-end,
 * validates it, writes it to storage/photos/, and returns
 * the public download URL.
 */
header('Content-Type: application/json');

/* ── Only accept POST ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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
