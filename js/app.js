/* ═══════════════════════════════════════════════════════
   Photo Booth — app.js
   Pure frontend: WebRTC capture → Fabric.js layout → A3 print
   ═══════════════════════════════════════════════════════ */

'use strict';

/* ─────────────────────────────────────────────────────────
   CONSTANTS
   ───────────────────────────────────────────────────────── */
const A3_W          = 3508;         // A3 portrait at 300 dpi
const A3_H          = 4961;
const TOTAL_SHOTS   = 3;
const COUNTDOWN_SEC = 3;

/* ─────────────────────────────────────────────────────────
   STATE
   ───────────────────────────────────────────────────────── */
let capturedPhotos  = [];   // base64 JPEG data-URLs
let mediaStream     = null;
let captureActive   = false;
let draggedPhotoIdx = null;                    // tray item currently being dragged
let slotAssignments = [null, null, null];      // which photoIdx lives in each drop slot


/* ─────────────────────────────────────────────────────────
   DOM REFERENCES
   ───────────────────────────────────────────────────────── */
const $ = id => document.getElementById(id);

// Screens
const screenStart     = $('screen-start');
const screenIntro     = $('screen-intro');
const screenCountdown = $('screen-countdown');
const screenReview    = $('screen-review');
const screenArrange   = $('screen-arrange');
const screenConfirm   = $('screen-confirm');
const screenFinal     = $('screen-final');

// Countdown screen elements
const videoEl       = $('camera-video');
const captureCanvas = $('capture-canvas');
const flashOverlay  = $('flash-overlay');
const cdArea        = $('cd-area');
const cdShotLabel   = $('cd-shot-label');
const cdNumber      = $('cd-number');
const cdReveal      = $('cd-reveal');
const cdRevealTitle = $('cd-reveal-title');
const cdPhoto       = $('cd-photo');
const btnCdNext     = $('btn-cd-next');

// Loading
const loadingOverlay = $('loading-overlay');
const loadingText    = $('loading-text');

// Buttons
const btnStart = $('btn-start');


/* ─────────────────────────────────────────────────────────
   UTILITIES
   ───────────────────────────────────────────────────────── */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function showLoading(msg = 'Loading…') {
    loadingText.textContent = msg;
    loadingOverlay.classList.remove('hidden');
}

function hideLoading() {
    loadingOverlay.classList.add('hidden');
}

function showScreen(name) {
    [screenStart, screenIntro, screenCountdown, screenReview, screenArrange, screenConfirm, screenFinal]
        .forEach(el => el.classList.remove('active'));
    $(`screen-${name}`).classList.add('active');
}


/* ─────────────────────────────────────────────────────────
   START SCREEN
   ───────────────────────────────────────────────────────── */
btnStart.addEventListener('click', handleStart);
$('btn-lets-go').addEventListener('click', () => startCamera());

function handleStart() {
    // Must be a secure context (HTTPS or localhost)
    if (!window.isSecureContext) {
        // Warning banner is already visible; shake the button as feedback
        btnStart.classList.add('shake');
        setTimeout(() => btnStart.classList.remove('shake'), 600);
        return;
    }

    // Fail fast if getUserMedia isn't available (very old browsers)
    if (!navigator?.mediaDevices?.getUserMedia) {
        alert('Your browser does not support camera access.\nPlease use Chrome, Firefox, or Edge (updated).');
        return;
    }

    showScreen('intro');
}


/* ─────────────────────────────────────────────────────────
   CAMERA — INITIALISATION
   ───────────────────────────────────────────────────────── */
async function startCamera() {
    showLoading('Starting camera…');

    // Reset state
    capturedPhotos = [];
    captureActive  = false;

    // Stop any previous stream
    stopCamera();

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                width:       { ideal: 1080 },
                height:      { ideal: 1920 },
                aspectRatio: { ideal: 9/16 },
                facingMode: 'user',
            },
            audio: false,
        });

        videoEl.srcObject = mediaStream;

        // Wait for metadata to be ready
        await new Promise(resolve => {
            if (videoEl.readyState >= 2) { resolve(); return; }
            videoEl.onloadedmetadata = resolve;
        });

        await videoEl.play();

        hideLoading();
        showScreen('countdown');

        await sleep(800);
        runCaptureSequence();

    } catch (err) {
        hideLoading();
        showScreen('start');
        const msg = err.name === 'NotAllowedError'
            ? 'Camera permission was denied.\n\nPlease allow camera access in your browser settings and try again.'
            : `Camera error: ${err.message}`;
        alert(msg);
    }
}

function stopCamera() {
    if (mediaStream) {
        mediaStream.getTracks().forEach(t => t.stop());
        mediaStream = null;
    }
    videoEl.srcObject = null;
}


/* ─────────────────────────────────────────────────────────
   CAMERA — CAPTURE SEQUENCE
   ───────────────────────────────────────────────────────── */
const SHOT_LABELS = ['First', 'Second', 'Third'];

function updateCdDots(activeIdx) {
    [0, 1, 2].forEach(i => {
        const dot = $(`cd-dot-${i}`);
        if (dot) dot.classList.toggle('active', i === activeIdx);
    });
}

async function runCaptureSequence() {
    captureActive = true;

    for (let i = 0; i < TOTAL_SHOTS; i++) {
        if (!captureActive) return;

        // Activate current dot
        updateCdDots(i);

        // Show countdown UI for this shot
        cdReveal.classList.add('hidden');
        cdArea.classList.remove('hidden');
        cdShotLabel.textContent = `${SHOT_LABELS[i]} Shot!`;
        cdNumber.textContent = '';

        await sleep(700);
        if (!captureActive) return;

        // Countdown 3 → 1
        await runCountdown(COUNTDOWN_SEC);
        if (!captureActive) return;

        // Snap
        const dataURL = captureFrame();
        capturedPhotos.push(dataURL);
        triggerFlash();

        // Show photo reveal
        cdArea.classList.add('hidden');
        cdRevealTitle.textContent = `THE ${SHOT_LABELS[i].toUpperCase()} SHOT!`;
        cdPhoto.src = dataURL;
        cdReveal.classList.remove('hidden');

        // Wait for NEXT tap
        await waitForNext();
    }

    captureActive = false;
    stopCamera();
    initReviewScreen();
}

function waitForNext() {
    return new Promise(resolve => {
        btnCdNext.addEventListener('click', resolve, { once: true });
    });
}

async function runCountdown(from) {
    for (let n = from; n >= 1; n--) {
        if (!captureActive) return;

        cdNumber.textContent = n;
        cdNumber.classList.remove('pop');
        void cdNumber.offsetWidth; // reflow to retrigger animation
        cdNumber.classList.add('pop');

        await sleep(1000);
    }
}

function captureFrame() {
    const ctx = captureCanvas.getContext('2d');
    const vw  = videoEl.videoWidth  || 1080;
    const vh  = videoEl.videoHeight || 1920;

    // Match exactly what object-fit:cover shows in the portrait container
    const containerW = videoEl.clientWidth  || vw;
    const containerH = videoEl.clientHeight || vh;
    const scale  = Math.max(containerW / vw, containerH / vh);
    const cropW  = containerW / scale;
    const cropH  = containerH / scale;
    const sx     = (vw - cropW) / 2;
    const sy     = (vh - cropH) / 2;

    captureCanvas.width  = Math.round(cropW);
    captureCanvas.height = Math.round(cropH);

    // Mirror horizontally to match the mirrored preview
    ctx.save();
    ctx.translate(captureCanvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(videoEl, sx, sy, cropW, cropH, 0, 0, captureCanvas.width, captureCanvas.height);
    ctx.restore();

    return captureCanvas.toDataURL('image/jpeg', 0.92);
}

function triggerFlash() {
    flashOverlay.classList.remove('flash-active');
    void flashOverlay.offsetWidth; // reflow
    flashOverlay.classList.add('flash-active');
    setTimeout(() => flashOverlay.classList.remove('flash-active'), 600);
}




/* ─────────────────────────────────────────────────────────
   REVIEW SCREEN
   ───────────────────────────────────────────────────────── */
function initReviewScreen() {
    const strip = $('review-strip');
    strip.innerHTML = '';

    capturedPhotos.forEach((dataURL, i) => {
        const wrap = document.createElement('div');
        wrap.className = 'review-photo-wrap';

        const img = document.createElement('img');
        img.src = dataURL;
        img.alt = `Photo ${i + 1}`;

        wrap.appendChild(img);
        strip.appendChild(wrap);
    });

    showScreen('review');
}

$('btn-review-retake').addEventListener('click', () => {
    captureActive = false;
    stopCamera();
    showScreen('intro');
});
$('btn-review-next').addEventListener('click', initArrangeScreen);


/* ─────────────────────────────────────────────────────────
   ARRANGE SCREEN — INIT
   ───────────────────────────────────────────────────────── */
async function initArrangeScreen() {
    showLoading('PREPARING LAYOUT…');
    showScreen('arrange');
    await sleep(80);

    // Reset drag state
    draggedPhotoIdx = null;
    slotAssignments = [null, null, null];

    // Populate tray with captured photos
    capturedPhotos.forEach((dataURL, i) => {
        const img  = document.querySelector(`.tray-item[data-idx="${i}"] img`);
        const item = document.querySelector(`.tray-item[data-idx="${i}"]`);
        if (img)  img.src = dataURL;
        if (item) item.classList.remove('used');
    });

    // Clear all drop slots
    document.querySelectorAll('.drop-slot').forEach(slot => {
        slot.classList.remove('filled');
        const img  = slot.querySelector('img');
        const hint = slot.querySelector('.slot-hint');
        if (img)  img.remove();
        if (hint) hint.style.display = '';
    });

    // Reset confirm button
    const btn = $('btn-confirm');
    btn.textContent = 'CONFIRM';
    btn.classList.remove('done-mode');
    [$('btn-print-final'), $('btn-download-final')].forEach(b => b && b.classList.add('hidden'));

    hideLoading();
}

// Back button from arrange screen
$('btn-arrange-back').addEventListener('click', initReviewScreen);


/* ─────────────────────────────────────────────────────────
   ARRANGE — DRAG & DROP  (mouse + touch)
   ───────────────────────────────────────────────────────── */

/* ── Mouse drag (desktop) ── */
document.addEventListener('dragstart', e => {
    const item = e.target.closest('.tray-item');
    if (!item) return;
    draggedPhotoIdx = parseInt(item.dataset.idx);
    e.dataTransfer.effectAllowed = 'copy';
    item.classList.add('dragging');
});

document.addEventListener('dragend', e => {
    const item = e.target.closest('.tray-item');
    if (item) item.classList.remove('dragging');
    draggedPhotoIdx = null;
});

document.addEventListener('dragover', e => {
    const slot = e.target.closest('.drop-slot');
    if (!slot) return;
    e.preventDefault();
    slot.classList.add('drag-over');
});

document.addEventListener('dragleave', e => {
    const slot = e.target.closest('.drop-slot');
    if (!slot) return;
    if (!slot.contains(e.relatedTarget)) slot.classList.remove('drag-over');
});

document.addEventListener('drop', e => {
    const slot = e.target.closest('.drop-slot');
    if (!slot) return;
    e.preventDefault();
    slot.classList.remove('drag-over');
    if (draggedPhotoIdx === null) return;
    placePhotoInSlot(slot, draggedPhotoIdx);
    draggedPhotoIdx = null;
});

/* ── Touch drag (mobile / touchscreen) ── */
let _touchDragIdx  = null;   // photoIdx being dragged
let _touchClone    = null;   // floating ghost image
let _touchLastSlot = null;   // slot currently hovered

document.addEventListener('touchstart', e => {
    const item = e.target.closest('.tray-item');
    if (!item) return;
    e.preventDefault(); // stop tray scroll-container from stealing the touch
    _touchDragIdx = parseInt(item.dataset.idx);
    item.classList.add('dragging');

    // Build ghost clone
    const rect = item.getBoundingClientRect();
    const srcImg = item.querySelector('img');
    _touchClone = srcImg ? srcImg.cloneNode(true) : document.createElement('div');
    Object.assign(_touchClone.style, {
        position:      'fixed',
        left:          rect.left + 'px',
        top:           rect.top  + 'px',
        width:         rect.width  + 'px',
        height:        rect.height + 'px',
        objectFit:     'cover',
        opacity:       '0.80',
        pointerEvents: 'none',
        zIndex:        '99999',
        borderRadius:  '0',
        boxShadow:     '0 8px 24px rgba(0,0,0,.35)',
    });
    document.body.appendChild(_touchClone);
}, { passive: false });

document.addEventListener('touchmove', e => {
    if (_touchDragIdx === null) return;
    e.preventDefault();   // stop page scroll / reload while dragging

    const touch = e.touches[0];

    // Move ghost
    if (_touchClone) {
        _touchClone.style.left = (touch.clientX - parseInt(_touchClone.style.width)  / 2) + 'px';
        _touchClone.style.top  = (touch.clientY - parseInt(_touchClone.style.height) / 2) + 'px';
    }

    // Highlight slot under finger
    _touchClone && (_touchClone.style.display = 'none');
    const elUnder = document.elementFromPoint(touch.clientX, touch.clientY);
    _touchClone && (_touchClone.style.display = '');

    const slot = elUnder ? elUnder.closest('.drop-slot') : null;

    if (slot !== _touchLastSlot) {
        if (_touchLastSlot) _touchLastSlot.classList.remove('drag-over');
        if (slot)           slot.classList.add('drag-over');
        _touchLastSlot = slot;
    }
}, { passive: false });

document.addEventListener('touchend', e => {
    if (_touchDragIdx === null) return;

    // Remove ghost
    if (_touchClone) { _touchClone.remove(); _touchClone = null; }

    // Clear dragging style on source
    const srcItem = document.querySelector(`.tray-item[data-idx="${_touchDragIdx}"]`);
    if (srcItem) srcItem.classList.remove('dragging');

    // Clear slot highlight
    if (_touchLastSlot) _touchLastSlot.classList.remove('drag-over');

    // Drop
    if (_touchLastSlot) {
        placePhotoInSlot(_touchLastSlot, _touchDragIdx);
    }

    _touchDragIdx  = null;
    _touchLastSlot = null;
}, { passive: true });

document.addEventListener('touchcancel', () => {
    if (_touchClone) { _touchClone.remove(); _touchClone = null; }
    const srcItem = _touchDragIdx !== null
        ? document.querySelector(`.tray-item[data-idx="${_touchDragIdx}"]`) : null;
    if (srcItem) srcItem.classList.remove('dragging');
    if (_touchLastSlot) _touchLastSlot.classList.remove('drag-over');
    _touchDragIdx  = null;
    _touchLastSlot = null;
}, { passive: true });

function placePhotoInSlot(slot, photoIdx) {
    const slotIdx = parseInt(slot.dataset.slot);

    // Unmark the previous occupant of this slot
    const prevInSlot = slotAssignments[slotIdx];
    if (prevInSlot !== null) {
        const prevItem = document.querySelector(`.tray-item[data-idx="${prevInSlot}"]`);
        if (prevItem) prevItem.classList.remove('used');
    }

    // If this photo was already placed in another slot, clear that slot
    const prevSlotOfPhoto = slotAssignments.indexOf(photoIdx);
    if (prevSlotOfPhoto !== -1 && prevSlotOfPhoto !== slotIdx) {
        slotAssignments[prevSlotOfPhoto] = null;
        const prevSlotEl = document.querySelector(`.drop-slot[data-slot="${prevSlotOfPhoto}"]`);
        if (prevSlotEl) {
            prevSlotEl.classList.remove('filled');
            const old  = prevSlotEl.querySelector('img');
            const hint = prevSlotEl.querySelector('.slot-hint');
            if (old)  old.remove();
            if (hint) hint.style.display = '';
        }
    }

    // Assign photo to slot
    slotAssignments[slotIdx] = photoIdx;
    const trayItem = document.querySelector(`.tray-item[data-idx="${photoIdx}"]`);
    if (trayItem) trayItem.classList.add('used');

    // Render photo in slot
    slot.classList.add('filled');
    const hint = slot.querySelector('.slot-hint');
    if (hint) hint.style.display = 'none';

    let img = slot.querySelector('img');
    if (!img) {
        img = document.createElement('img');
        img.draggable = false;
        img.alt = `Photo ${photoIdx + 1}`;
        slot.appendChild(img);
    }
    img.src = capturedPhotos[photoIdx];
}


/* ─────────────────────────────────────────────────────────
   ARRANGE — CONFIRM → CONFIRM SCREEN
   ───────────────────────────────────────────────────────── */
let collageDataURL = null;

$('btn-confirm').addEventListener('click', handleConfirm);

async function handleConfirm() {
    // Require all 3 slots filled
    const filled = slotAssignments.filter(s => s !== null).length;
    if (filled < 3) {
        alert('Please place all 3 photos before confirming.');
        return;
    }

    showLoading('GENERATING COLLAGE…');
    await sleep(80);

    try {
        collageDataURL = await exportCollage(0.95);

        // Save to server
        const response = await fetch('save.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ image: collageDataURL }),
        });
        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.error || `Server error ${response.status}`);
        }
        const { url } = await response.json();
        collageServerURL = url;

        // Show confirm screen
        $('confirm-preview-img').src = collageDataURL;
        hideLoading();
        showScreen('confirm');

    } catch (err) {
        hideLoading();
        console.error('Collage error:', err);
        alert('Could not generate collage.\n\n' + err.message);
    }
}

let collageServerURL = '';

// RETURN from confirm screen → back to arrange
$('btn-confirm-back').addEventListener('click', () => showScreen('arrange'));

// CONFIRM on confirm screen → show final screen
$('btn-confirm-proceed').addEventListener('click', () => {
    $('final-preview-img').src = collageDataURL;
    showScreen('final');
});

/* ─────────────────────────────────────────────────────────
   FINAL SCREEN
   ───────────────────────────────────────────────────────── */
$('btn-final-done').addEventListener('click', () => {
    capturedPhotos  = [];
    slotAssignments = [null, null, null];
    collageDataURL  = null;
    collageServerURL = '';
    showScreen('start');
});

$('btn-final-download').addEventListener('click', handleQRFinal);
$('btn-final-print').addEventListener('click', () => {
    printCopies = 1;
    $('copies-value').textContent = '1';
    $('print-modal').classList.remove('hidden');
});

async function handleQRFinal() {
    if (collageServerURL) {
        showQRModal(collageServerURL);
        return;
    }
    // Fallback: re-save
    showLoading('SAVING PHOTO…');
    await sleep(60);
    try {
        const dataURL = collageDataURL || await exportCollage(0.95);
        const response = await fetch('save.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ image: dataURL }),
        });
        if (!response.ok) throw new Error(`Server error ${response.status}`);
        const { url } = await response.json();
        collageServerURL = url;
        hideLoading();
        showQRModal(url);
    } catch (err) {
        hideLoading();
        alert('Could not save photo.\n\n' + err.message);
    }
}


/* ─────────────────────────────────────────────────────────
   ARRANGE — EXPORT  (compose A3 image on a hidden canvas)
   ───────────────────────────────────────────────────────── */
async function exportCollage(quality = 0.95) {
    const board     = $('collage-board');
    const boardRect = board.getBoundingClientRect();

    const canvas  = document.createElement('canvas');
    canvas.width  = A3_W;
    canvas.height = A3_H;
    const ctx = canvas.getContext('2d');

    // Draw the collage template as background
    const templateImg = new Image();
    templateImg.src = 'asset/template.webp';
    await new Promise(resolve => {
        if (templateImg.complete && templateImg.naturalWidth) { resolve(); return; }
        templateImg.onload  = resolve;
        templateImg.onerror = resolve;
    });
    ctx.drawImage(templateImg, 0, 0, A3_W, A3_H);

    const scaleX = A3_W / boardRect.width;
    const scaleY = A3_H / boardRect.height;

    for (const slot of document.querySelectorAll('.drop-slot')) {
        const slotImg = slot.querySelector('img');
        if (!slotImg || !slotImg.src) continue;

        const slotRect = slot.getBoundingClientRect();
        const dx = (slotRect.left - boardRect.left) * scaleX;
        const dy = (slotRect.top  - boardRect.top)  * scaleY;
        const dw = slotRect.width  * scaleX;
        const dh = slotRect.height * scaleY;

        const srcImg = new Image();
        srcImg.src = slotImg.src;
        await new Promise(resolve => {
            if (srcImg.complete && srcImg.naturalWidth) { resolve(); return; }
            srcImg.onload = resolve;
        });

        // object-fit: cover scaling
        const scale = Math.max(dw / srcImg.naturalWidth, dh / srcImg.naturalHeight);
        const sw    = srcImg.naturalWidth  * scale;
        const sh    = srcImg.naturalHeight * scale;
        const sx    = dx + (dw - sw) / 2;
        const sy    = dy + (dh - sh) / 2;

        ctx.save();
        ctx.beginPath();
        ctx.rect(dx, dy, dw, dh);
        ctx.clip();
        ctx.drawImage(srcImg, sx, sy, sw, sh);
        ctx.restore();
    }

    return canvas.toDataURL('image/jpeg', quality);
}


/* ─────────────────────────────────────────────────────────
   PRINT — A3 OUTPUT
   ───────────────────────────────────────────────────────── */
/* ─────────────────────────────────────────────────────────
   PRINT — COPIES MODAL
   ───────────────────────────────────────────────────────── */
let printCopies = 1;

$('btn-print-final').addEventListener('click', () => {
    printCopies = 1;
    $('copies-value').textContent = '1';
    $('print-modal').classList.remove('hidden');
});

$('btn-copies-dec').addEventListener('click', () => {
    if (printCopies > 1) $('copies-value').textContent = --printCopies;
});

$('btn-copies-inc').addEventListener('click', () => {
    if (printCopies < 99) $('copies-value').textContent = ++printCopies;
});

$('btn-print-cancel').addEventListener('click', () => {
    $('print-modal').classList.add('hidden');
});

$('btn-print-confirm').addEventListener('click', () => {
    $('print-modal').classList.add('hidden');
    handlePrint(printCopies);
});

async function rotateDataURL180(dataURL) {
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
        img.src = dataURL;
    });
}

async function handlePrint(copies = 1) {
    showLoading('GENERATING A3 AT 300 DPI…');
    await sleep(80);

    try {
        const raw      = collageDataURL || await exportCollage(0.95);
        const dataURL  = await rotateDataURL180(raw);
        const printArea = $('print-area');

        // Build one .print-page per copy — browser prints each as a separate page
        printArea.innerHTML = '';
        for (let i = 0; i < copies; i++) {
            const page = document.createElement('div');
            page.className = 'print-page';
            const img = document.createElement('img');
            img.src = dataURL;
            img.alt = 'A3 Photo Layout';
            page.appendChild(img);
            printArea.appendChild(page);
        }

        // Wait for all images to decode
        await Promise.all(
            Array.from(printArea.querySelectorAll('img')).map(
                img => img.decode ? img.decode() : Promise.resolve()
            )
        );

        hideLoading();
        await sleep(100);
        window.print();

    } catch (err) {
        hideLoading();
        console.error('Print error:', err);
        alert('Failed to generate print image.\n\n' + err.message);
    }
}


/* ─────────────────────────────────────────────────────────
   QR CODE — SHOW DOWNLOAD QR
   ───────────────────────────────────────────────────────── */

function showQRModal(url) {
    const modal   = $('qr-modal');
    const urlText = $('qr-url-text');
    const wrap    = $('qr-canvas');

    urlText.textContent = url;
    modal.classList.remove('hidden');

    // qrcode-generator API
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();

    // createImgTag(cellSize, margin, alt)
    wrap.innerHTML = qr.createImgTag(4, 4, 'Download QR code');
    const img = wrap.querySelector('img');
    if (img) img.style.cssText = 'display:block;width:220px;height:220px;image-rendering:pixelated';

    $('btn-qr-close').onclick = () => modal.classList.add('hidden');
}


/* ─────────────────────────────────────────────────────────
   BOOT
   ───────────────────────────────────────────────────────── */
(function checkSecureContext() {
    const badge   = $('origin-badge');
    const warning = $('insecure-warning');
    const urlEl   = $('iw-url');

    const host    = window.location.hostname;
    const secure  = window.isSecureContext;

    // Show current origin in the footer for quick debugging
    if (badge) badge.textContent = window.location.origin;

    if (!secure) {
        // Show warning banner
        if (warning) warning.classList.remove('hidden');

        // Suggest the Laravel Herd .test HTTPS URL.
        // Convention: folder name becomes the subdomain, e.g. /camera → https://camera.test
        if (urlEl) {
            // Derive site name from the first path segment or hostname
            const pathParts = window.location.pathname.split('/').filter(Boolean);
            const siteName  = pathParts[0] || host.split('.')[0] || 'camera';
            urlEl.textContent = `https://${siteName}.test`;
        }

        // Visually disable the Start button
        btnStart.classList.add('disabled');
    }
})();

showScreen('start');
