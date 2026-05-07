<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Security meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'">
    <title>Tommy Hilfiger · Photo Shoot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- ══════════════════════════════════════════════ -->
    <!--                  START SCREEN                  -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="screen-start" class="screen active">

        <!-- Header logo: Hosted by Hilfiger — Summer Gazette 2026 -->
        <div class="start-header">
            <img class="start-header-img" src="asset/header.webp" alt="Hosted by Hilfiger — Summer Gazette 2026">
        </div>

        <!-- Stacked promo image -->
        <div class="start-middle">
            <img class="start-combine-img" src="asset/combine.png" alt="">
        </div>

        <!-- CTA -->
        <div class="start-cta">
            <button id="btn-start" class="btn-start-box">START</button>
        </div>

        <!-- Footer: Tommy Hilfiger logo -->
        <div class="start-footer-bar">
            <img class="start-footer-logo" src="asset/logo.png" alt="Tommy Hilfiger">
        </div>

        <!-- Shown only when page is not in a secure context -->
        <div id="insecure-warning" class="insecure-warning hidden">
            <div class="iw-icon">⚠️</div>
            <div class="iw-body">
                <strong>Camera unavailable — insecure origin</strong>
                <p>
                    Browsers only allow camera access over <code>https://</code> or <code>localhost</code>.
                    Your current URL uses plain <code>http://</code>.
                </p>
                <p class="iw-fix">With Laravel Herd, open the app at:</p>
                <code class="iw-url" id="iw-url">https://camera.test</code>
                <p class="iw-alt">Herd automatically provisions HTTPS for <code>.test</code> sites.</p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--                  INTRO SCREEN                  -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="screen-intro" class="screen">

        <!-- Header logo -->
        <div class="start-header">
            <img class="start-header-img" src="asset/header.webp" alt="Hosted by Hilfiger — Summer Gazette 2026">
        </div>

        <!-- Middle: instruction text -->
        <div class="intro-middle">
            <p class="intro-text">GET READY! 3 SHOTS COMING<br>POSE WITH EVERY COUNTDOWN!</p>
        </div>

        <!-- CTA -->
        <div class="start-cta">
            <button id="btn-lets-go" class="btn-start-box">LET'S GO</button>
        </div>

        <!-- Footer -->
        <div class="start-footer-bar">
            <img class="start-footer-logo" src="asset/logo.png" alt="Tommy Hilfiger">
        </div>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--                 CAMERA SCREEN                  -->
    <!-- ══════════════════════════════════════════════ -->
    <!--               COUNTDOWN SCREEN                 -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="screen-countdown" class="screen">

        <!-- Header -->
        <div class="start-header">
            <img class="start-header-img" src="asset/header.webp" alt="Hosted by Hilfiger — Summer Gazette 2026">
        </div>

        <!-- Countdown area -->
        <div id="cd-area" class="cd-area">
            <!-- Live camera fill -->
            <div class="cd-camera-wrap">
                <video id="camera-video" autoplay playsinline muted class="cd-camera-video"></video>
            </div>
            <!-- Overlay text -->
            <div class="cd-overlay">
                <p id="cd-shot-label" class="cd-shot-label">First Shot!</p>
                <p class="cd-get-ready">Get Ready!</p>
                <div id="cd-number" class="cd-number"></div>
            </div>
        </div>

        <!-- Photo reveal (shown after each shot) -->
        <div id="cd-reveal" class="cd-reveal hidden">
            <p id="cd-reveal-title" class="cd-reveal-title">THE FIRST SHOT!</p>
            <div class="cd-photo-wrap">
                <img id="cd-photo" class="cd-photo" alt="">
                <button id="btn-cd-next" class="btn-start-box cd-next-btn">NEXT</button>
            </div>
        </div>

        <!-- Shot dot indicators -->
        <div class="cd-dots">
            <span class="cd-dot" id="cd-dot-0"></span>
            <span class="cd-dot" id="cd-dot-1"></span>
            <span class="cd-dot" id="cd-dot-2"></span>
        </div>

        <!-- Footer -->
        <div class="start-footer-bar">
            <img class="start-footer-logo" src="asset/logo.png" alt="Tommy Hilfiger">
        </div>

        <!-- Hidden capture canvas -->
        <canvas id="capture-canvas" style="display:none"></canvas>

        <!-- Flash overlay -->
        <div id="flash-overlay" class="flash-overlay"></div>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--                 REVIEW SCREEN                  -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="screen-review" class="screen">

        <!-- Header -->
        <div class="review-header">
            <button id="btn-review-retake" class="btn-start-box review-retake-btn">RETAKE</button>
            <img class="start-header-img" src="asset/header.webp" alt="Hosted by Hilfiger — Summer Gazette 2026">
        </div>

        <!-- Title -->
        <h2 class="review-title">THE FINAL PHOTOS</h2>

        <!-- Photo grid: 2 top, 1 bottom-centre -->
        <div class="review-grid" id="review-strip"></div>

        <!-- CTA -->
        <div class="start-cta">
            <button id="btn-review-next" class="btn-start-box">NEXT</button>
        </div>

        <!-- Footer -->
        <div class="start-footer-bar">
            <img class="start-footer-logo" src="asset/logo.png" alt="Tommy Hilfiger">
        </div>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--                 ARRANGE SCREEN                  -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="screen-arrange" class="screen">

        <!-- Header logo -->
        <div class="start-header">
            <img class="start-header-img" src="asset/header.webp" alt="Hosted by Hilfiger — Summer Gazette 2026">
        </div>

        <!-- Compact control row: UNDO + instruction -->
        <div class="arrange-header">
            <button id="btn-arrange-back" class="btn-start-box arrange-undo-btn">UNDO</button>
            <span class="arrange-header-title">Drag the photo to your preferred spot</span>
        </div>

        <!-- Body: photo tray + collage board -->
        <div class="arrange-body">

            <!-- Left: 3 source photos to drag from -->
            <div class="photo-tray" id="photo-tray">
                <div class="tray-item" draggable="true" data-idx="0">
                    <img alt="Photo 1">
                </div>
                <div class="tray-item" draggable="true" data-idx="1">
                    <img alt="Photo 2">
                </div>
                <div class="tray-item" draggable="true" data-idx="2">
                    <img alt="Photo 3">
                </div>
            </div>

            <!-- Right: collage board — template.webp background + drop zones -->
            <div class="collage-board" id="collage-board">
                <div class="board-slots">
                    <div class="drop-slot" data-slot="0">
                        <span class="slot-hint">DRAG YOUR<br>PHOTO HERE</span>
                    </div>
                    <div class="drop-slot" data-slot="1">
                        <span class="slot-hint">DRAG YOUR<br>PHOTO HERE</span>
                    </div>
                    <div class="drop-slot" data-slot="2">
                        <span class="slot-hint">DRAG YOUR<br>PHOTO HERE</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer: confirm + logo -->
        <div class="start-cta" style="padding-top:8px;padding-bottom:8px;">
            <button id="btn-confirm" class="btn-start-box">CONFIRM</button>
        </div>
        <div class="start-footer-bar" style="padding-bottom:16px;">
            <img class="start-footer-logo" src="asset/logo.png" alt="Tommy Hilfiger">
        </div>

        <!-- Hidden action buttons (kept for JS compatibility) -->
        <button id="btn-print-final"    class="hidden" style="display:none"></button>
        <button id="btn-download-final" class="hidden" style="display:none"></button>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--               CONFIRM SCREEN                   -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="screen-confirm" class="screen">

        <!-- Control row -->
        <div class="confirm-header">
            <button id="btn-confirm-back" class="btn-start-box confirm-back-btn">RETURN</button>
        </div>

        <!-- Title -->
        <p class="confirm-voila">Voilà! You're Done</p>

        <!-- Collage preview -->
        <div class="confirm-preview-wrap">
            <img id="confirm-preview-img" class="confirm-preview-img" alt="Your collage">
        </div>

        <!-- CTA -->
        <div class="start-cta" style="padding-top:8px;padding-bottom:8px;">
            <button id="btn-confirm-proceed" class="btn-start-box">CONFIRM</button>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--               FINAL SCREEN                     -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="screen-final" class="screen">

        <!-- Control row: download + print -->
        <div class="final-header">
            <div class="final-actions-top">
                <button id="btn-final-download" class="btn-start-box final-action-btn">DOWNLOAD <span class="final-btn-icon">&#x1F4F1;</span></button>
                <button id="btn-final-print"    class="btn-start-box final-action-btn">PRINT OUT <span class="final-btn-icon">&#128438;</span></button>
            </div>
        </div>

        <!-- Collage preview -->
        <div class="confirm-preview-wrap">
            <img id="final-preview-img" class="confirm-preview-img" alt="Your collage">
        </div>

        <!-- CTA -->
        <div class="start-cta" style="padding-top:8px;padding-bottom:8px;">
            <button id="btn-final-done" class="btn-start-box">DONE</button>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--               PRINT COPIES MODAL               -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="print-modal" class="qr-modal hidden">
        <div class="qr-card print-modal-card">
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
                    <button id="btn-print-confirm" class="btn-primary">PRINT</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--                 QR DOWNLOAD MODAL               -->
    <!-- ══════════════════════════════════════════════ -->
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
                <button id="btn-qr-close" class="btn-primary">CLOSE</button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--               GLOBAL LOADING OVERLAY           -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="loading-overlay" class="loading-overlay hidden">
        <div class="spinner"></div>
        <p id="loading-text">Loading…</p>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!--            PRINT AREA (hidden except print)    -->
    <!-- ══════════════════════════════════════════════ -->
    <div id="print-area" class="print-area"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
