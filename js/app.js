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
const screenStart  = $('screen-start');
const screenCamera = $('screen-camera');
const screenReview = $('screen-review');
const screenArrange = $('screen-arrange');

// Camera screen
const videoEl          = $('camera-video');
const captureCanvas    = $('capture-canvas');
const countdownOverlay = $('countdown-overlay');
const countdownNum     = $('countdown-number');
const flashOverlay     = $('flash-overlay');
const cameraStatus     = $('camera-status');
const shotCurrent      = $('shot-current');
const dots             = [1, 2, 3].map(n => $(`ind-${n}`));
const stripCells       = [1, 2, 3].map(n => $(`strip-${n}`));

// Loading
const loadingOverlay = $('loading-overlay');
const loadingText    = $('loading-text');

// Buttons
const btnStart        = $('btn-start');
const btnRetakeCamera = $('btn-retake-camera');


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
    [screenStart, screenCamera, screenReview, screenArrange].forEach(el => el.classList.remove('active'));
    $(`screen-${name}`).classList.add('active');
}


/* ─────────────────────────────────────────────────────────
   START SCREEN
   ───────────────────────────────────────────────────────── */
btnStart.addEventListener('click', handleStart);

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

    startCamera();
}


/* ─────────────────────────────────────────────────────────
   CAMERA — INITIALISATION
   ───────────────────────────────────────────────────────── */
async function startCamera() {
    showLoading('Starting camera…');

    // Reset state
    capturedPhotos = [];
    captureActive  = false;
    shotCurrent.textContent = '0';

    dots.forEach(d => d.classList.remove('filled'));
    stripCells.forEach((cell, i) => {
        cell.innerHTML = `<span class="cell-label">${i + 1}</span>`;
        cell.classList.remove('filled');
    });

    // Stop any previous stream
    stopCamera();

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                width:      { ideal: 1280 },
                height:     { ideal: 720  },
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
        showScreen('camera');
        updateStatus('READY');

        await sleep(1400);
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

btnRetakeCamera.addEventListener('click', () => {
    captureActive = false;
    stopCamera();
    showScreen('start');
});


/* ─────────────────────────────────────────────────────────
   CAMERA — CAPTURE SEQUENCE
   ───────────────────────────────────────────────────────── */
async function runCaptureSequence() {
    captureActive = true;

    for (let i = 0; i < TOTAL_SHOTS; i++) {
        if (!captureActive) return;

        updateStatus(`SHOT ${i + 1} OF ${TOTAL_SHOTS} — SMILE`);
        await sleep(300);
        if (!captureActive) return;

        // Countdown 3 → 2 → 1
        await runCountdown(COUNTDOWN_SEC);
        if (!captureActive) return;

        // Snap the frame
        const dataURL = captureFrame();
        capturedPhotos.push(dataURL);

        // Visual feedback
        triggerFlash();
        shotCurrent.textContent = i + 1;
        updateStripCell(i, dataURL);
        dots[i].classList.add('filled');

        if (i < TOTAL_SHOTS - 1) {
            updateStatus('PERFECT — NEXT SHOT');
            await sleep(1100);
        }
    }

    if (captureActive) {
        updateStatus('ALL SHOTS CAPTURED');
        captureActive = false;
        await sleep(700);
        stopCamera();
        initReviewScreen();
    }
}

async function runCountdown(from) {
    countdownOverlay.classList.remove('hidden');

    for (let n = from; n >= 1; n--) {
        if (!captureActive) {
            countdownOverlay.classList.add('hidden');
            return;
        }

        countdownNum.textContent = n;

        // Re-trigger CSS animation by forcing reflow
        countdownNum.classList.remove('pop');
        void countdownNum.offsetWidth;
        countdownNum.classList.add('pop');

        await sleep(1000);
    }

    countdownOverlay.classList.add('hidden');
}

function captureFrame() {
    const ctx = captureCanvas.getContext('2d');
    const vw  = videoEl.videoWidth  || 1280;
    const vh  = videoEl.videoHeight || 720;

    captureCanvas.width  = vw;
    captureCanvas.height = vh;

    // Mirror horizontally so the saved image matches the mirrored preview
    ctx.save();
    ctx.translate(vw, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(videoEl, 0, 0, vw, vh);
    ctx.restore();

    return captureCanvas.toDataURL('image/jpeg', 0.92);
}

function triggerFlash() {
    flashOverlay.classList.remove('flash-active');
    void flashOverlay.offsetWidth; // reflow
    flashOverlay.classList.add('flash-active');
    setTimeout(() => flashOverlay.classList.remove('flash-active'), 600);
}

function updateStripCell(idx, dataURL) {
    const cell = stripCells[idx];
    const img  = document.createElement('img');
    img.src    = dataURL;
    cell.innerHTML = '';
    cell.appendChild(img);
    cell.classList.add('filled');
}

function updateStatus(msg) {
    cameraStatus.textContent = msg;
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

        const num = document.createElement('span');
        num.className = 'review-photo-num';
        num.textContent = i + 1;

        wrap.appendChild(img);
        wrap.appendChild(num);
        strip.appendChild(wrap);
    });

    showScreen('review');
}

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
   ARRANGE — DRAG & DROP  (event delegation, registered once)
   ───────────────────────────────────────────────────────── */
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
   ARRANGE — CONFIRM / DONE
   ───────────────────────────────────────────────────────── */
$('btn-confirm').addEventListener('click', handleConfirmOrDone);

function handleConfirmOrDone() {
    const btn = $('btn-confirm');

    if (!btn.classList.contains('done-mode')) {
        btn.textContent = 'DONE';
        btn.classList.add('done-mode');
        [$('btn-print-final'), $('btn-download-final')].forEach(b => {
            if (b) b.classList.remove('hidden');
        });
    } else {
        capturedPhotos  = [];
        slotAssignments = [null, null, null];
        showScreen('start');
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

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, A3_W, A3_H);

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

async function handlePrint(copies = 1) {
    showLoading('GENERATING A3 AT 300 DPI…');
    await sleep(80);

    try {
        const dataURL  = await exportCollage(0.95);
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
   QR CODE — SAVE TO SERVER & SHOW DOWNLOAD QR
   ───────────────────────────────────────────────────────── */
$('btn-download-final').addEventListener('click', handleQR);

async function handleQR() {
    showLoading('SAVING PHOTO…');
    await sleep(60);

    try {
        const dataURL = await exportCollage(0.95);

        const response = await fetch('save.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ image: dataURL }),
        });

        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.error || `Server error ${response.status}`);
        }

        const { url } = await response.json();
        hideLoading();
        showQRModal(url);

    } catch (err) {
        hideLoading();
        console.error('QR save error:', err);
        alert('Could not save photo.\n\n' + err.message);
    }
}

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
