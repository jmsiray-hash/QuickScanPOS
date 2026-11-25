<section class="code-scanner" aria-label="Product code scanner">
    <div class="row-actions scanner-controls">
        <input class="scanner-input" type="text" placeholder="Barcode or printed product code" autocomplete="off" autocapitalize="characters" aria-label="Barcode or printed product code">
        <button class="button scanner-add" type="button">Add Code</button>
        <button class="button button-secondary" id="code-scanner-focus" type="button">Focus Scanner</button>
    </div>
    <div class="row-actions scanner-modes">
        <button class="button button-secondary scanner-start-barcode" type="button">Scan Barcode with Camera</button>
        <button class="button button-secondary scanner-start-ocr" type="button">Read Printed Number / Can Code</button>
    </div>
    <div class="scanner-camera" hidden>
        <div class="scanner-reader"></div>
        <video class="scanner-video" playsinline muted hidden></video>
        <canvas class="scanner-canvas" hidden></canvas>
        <div class="row-actions">
            <button class="button scanner-capture" type="button" hidden>Read Printed Code</button>
            <button class="button button-secondary scanner-stop" type="button">Close Camera</button>
        </div>
    </div>
    <p class="scanner-message" role="status"></p>
</section>

<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.code-scanner').forEach((root, index) => {
            const input = root.querySelector('.scanner-input');
            const message = root.querySelector('.scanner-message');
            const camera = root.querySelector('.scanner-camera');
            const reader = root.querySelector('.scanner-reader');
            const video = root.querySelector('.scanner-video');
            const canvas = root.querySelector('.scanner-canvas');
            const capture = root.querySelector('.scanner-capture');
            let barcodeScanner = null;
            let stream = null;
            let ocrBusy = false;
            const recentlyScanned = new Map();

            reader.id = `mobile-code-reader-${index}`;

            function notify(text, isError = false) {
                message.textContent = text;
                message.className = 'scanner-message ' + (isError ? 'error' : 'success');
            }

            function cameraErrorMessage(error) {
                const detail = error && error.message ? error.message : String(error || '');
                const name = error && error.name ? error.name : '';

                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                    return 'Camera access requires HTTPS. Open this page using the forwarded HTTPS URL or localhost.';
                }
                if (name === 'NotAllowedError' || /permission denied|not allowed/i.test(detail)) {
                    return 'Camera permission was denied. Allow camera access for this site in your browser, then try again.';
                }
                if (name === 'NotFoundError' || /no camera|device not found/i.test(detail)) {
                    return 'No camera was found. Check that the phone has a working camera or enter the code manually.';
                }
                if (name === 'NotReadableError' || /camera.*in use|could not start video/i.test(detail)) {
                    return 'The camera could not start. Close other apps using it and try again.';
                }

                return detail || 'Camera unavailable. Check camera permissions or enter the code manually.';
            }

            function beep(isError = false) {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return;
                const context = new AudioContextClass();
                const oscillator = context.createOscillator();
                const gain = context.createGain();
                oscillator.frequency.value = isError ? 220 : 880;
                gain.gain.value = 0.06;
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start();
                oscillator.stop(context.currentTime + 0.1);
            }

            function emitCode(value) {
                const code = String(value || '').trim().toUpperCase();
                const now = Date.now();
                if (!code || now - (recentlyScanned.get(code) || 0) < 1200) return;
                recentlyScanned.set(code, now);
                input.value = '';
                notify('Scanned: ' + code);
                beep();
                document.dispatchEvent(new CustomEvent('pos:code-scanned', { detail: { code } }));
            }

            async function stopCamera() {
                if (barcodeScanner) {
                    await barcodeScanner.stop();
                    barcodeScanner.clear();
                    barcodeScanner = null;
                }
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
                video.srcObject = null;
                camera.hidden = true;
                reader.hidden = false;
                video.hidden = true;
                capture.hidden = true;
            }

            root.querySelector('.scanner-add').addEventListener('click', () => emitCode(input.value));
            root.querySelector('.scanner-input').addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    emitCode(input.value);
                }
            });
            root.querySelector('#code-scanner-focus').addEventListener('click', () => input.focus());

            root.querySelector('.scanner-start-barcode').addEventListener('click', async () => {
                camera.hidden = false;
                reader.hidden = false;
                video.hidden = true;
                capture.hidden = true;
                try {
                    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                        throw new Error('Camera access requires HTTPS. Open this page using the forwarded HTTPS URL or localhost.');
                    }
                    if (!window.Html5Qrcode) throw new Error('Barcode scanner library did not load.');
                    barcodeScanner = new Html5Qrcode(reader.id);
                    await barcodeScanner.start(
                        { facingMode: 'environment' },
                        {
                            fps: 15,
                            qrbox: (viewfinderWidth, viewfinderHeight) => {
                                const width = Math.min(Math.floor(viewfinderWidth * 0.9), 720);
                                const height = Math.min(Math.floor(viewfinderHeight * 0.42), Math.floor(width * 0.48));
                                return { width, height };
                            },
                            formatsToSupport: [
                                Html5QrcodeSupportedFormats.EAN_13,
                                Html5QrcodeSupportedFormats.EAN_8,
                                Html5QrcodeSupportedFormats.UPC_A,
                                Html5QrcodeSupportedFormats.UPC_E,
                                Html5QrcodeSupportedFormats.CODE_128,
                                Html5QrcodeSupportedFormats.CODE_39,
                                Html5QrcodeSupportedFormats.QR_CODE,
                            ],
                        },
                        decoded => emitCode(decoded),
                        () => {},
                    );
                } catch (error) {
                    notify(cameraErrorMessage(error), true);
                    beep(true);
                }
            });

            root.querySelector('.scanner-start-ocr').addEventListener('click', async () => {
                try {
                    if (!navigator.mediaDevices?.getUserMedia) throw new Error('Camera access requires HTTPS or localhost.');
                    await stopCamera();
                    camera.hidden = false;
                    reader.hidden = true;
                    video.hidden = false;
                    capture.hidden = false;
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
                    video.srcObject = stream;
                    await video.play();
                    notify('Point the camera at the printed number, then tap Read Printed Code.');
                } catch (error) {
                    notify(cameraErrorMessage(error), true);
                    beep(true);
                }
            });

            capture.addEventListener('click', async () => {
                if (ocrBusy || !video.videoWidth) return;
                ocrBusy = true;
                capture.disabled = true;
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                try {
                    if (!window.Tesseract) throw new Error('Printed-number reader did not load.');
                    const result = await Tesseract.recognize(canvas, 'eng');
                    const matches = String(result.data.text || '').toUpperCase().match(/[A-Z0-9][A-Z0-9-]{2,}/g) || [];
                    if (!matches.length) {
                        notify('No code was recognized. Try again or enter it manually.', true);
                        beep(true);
                    } else {
                        input.value = matches[0];
                        input.focus();
                        notify('Recognized ' + matches[0] + '. Review it, then tap Add Code.');
                        beep();
                    }
                } catch (error) {
                    notify(error.message || 'Could not read the printed code. Enter it manually.', true);
                    beep(true);
                } finally {
                    ocrBusy = false;
                    capture.disabled = false;
                }
            });

            root.querySelector('.scanner-stop').addEventListener('click', async () => {
                try {
                    await stopCamera();
                } catch (error) {
                    notify(error.message || 'The camera could not be closed.', true);
                }
            });

            window.addEventListener('pagehide', () => {
                if (stream) stream.getTracks().forEach(track => track.stop());
            });
        });
    });
</script>

<style>
    .code-scanner { display:grid; gap:10px; margin:18px 0; width:100%; }
    .scanner-controls input { flex:1; min-width:180px; border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:10px; }
    .scanner-camera { border:1px solid #dbe5e3; border-radius:8px; margin-top:8px; padding:12px; width:100%; }
    .scanner-reader,.scanner-video { max-width:100%; width:100%; }
    .scanner-reader video { height:auto !important; max-width:100% !important; width:100% !important; }
    .scanner-video { width:100%; }
    .scanner-message { min-height:1.5em; margin:0; }
    .scanner-message.error { color:#b91c1c; }
    .scanner-message.success { color:#166534; }
    @media(max-width:640px) {
        .scanner-controls,.scanner-modes { align-items:stretch; flex-direction:column; }
        .scanner-controls .button,.scanner-modes .button { min-height:44px; }
    }
</style>
