@csrf
<div class="field">
    <label for="barcode">Barcode / Printed Product Code</label>
    <div class="row-actions product-code-actions">
        <input id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" maxlength="100" required autofocus autocomplete="off">
        <button class="button button-secondary" type="button" id="focus-barcode">Focus Scanner</button>
        <button class="button" type="button" id="scan-barcode">Scan Barcode</button>
        <button class="button button-secondary" type="button" id="scan-with-phone">Scan with Phone</button>
        @include('components.bluetooth-scanner-help', ['targetId' => 'barcode'])
    </div>
    <section id="phone-scan-panel" class="phone-scan-panel" hidden>
        <h3>Connect a phone scanner</h3>
        <p>Scan this QR with your phone. The mobile scanner will send the code back to this product form.</p>
        <label for="phone-scan-url">URL the phone can reach</label>
        <input id="phone-scan-url" type="url" autocomplete="off">
        <div id="phone-scan-qr"></div>
        <p><a id="phone-scan-link" href="#" target="_blank" rel="noopener"></a></p>
        <p id="phone-scan-message" role="status"></p>
        <button class="button button-secondary" type="button" id="close-phone-scan">Close Phone Scanner</button>
    </section>
    <p class="field-hint">Use the printed number or product code here when an item has no barcode.</p>
    <p id="barcode-message" role="status"></p>
</div>
<div id="barcode-scanner" style="display:none;max-width:420px">
    <div id="product-reader"></div>
    <button class="button button-secondary" type="button" id="stop-product-scanner">Close scanner</button>
</div>
<div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $product->name) }}" required></div>
<div class="field"><label for="category">Category</label><input id="category" name="category" value="{{ old('category', $product->category) }}"></div>
<div class="field"><label for="cost_price">Cost price</label><input id="cost_price" name="cost_price" type="number" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price) }}" required></div>
<div class="field"><label for="selling_price">Selling price</label><input id="selling_price" name="selling_price" type="number" step="0.01" min="0" value="{{ old('selling_price', $product->selling_price) }}" required></div>
<div class="field"><label for="stock_quantity">Stock quantity</label><input id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required></div>
<label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> Active product</label>
<button class="button" type="submit">{{ $submit }}</button>

<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const codeInput = document.getElementById('barcode');
        const nameInput = document.getElementById('name');
        const message = document.getElementById('barcode-message');
        const panel = document.getElementById('barcode-scanner');
        let cameraScanner = null;
        let lastCode = '';
        let lastScannedAt = 0;
        let phonePollTimer = null;
        let phoneResultUrl = null;
        let phonePollInProgress = false;

        async function closeScanner() {
            if (!cameraScanner) return;
            await cameraScanner.stop();
            cameraScanner.clear();
            cameraScanner = null;
            panel.style.display = 'none';
        }

        async function lookup(rawCode) {
            const code = String(rawCode || '').trim().toUpperCase();
            if (!code) return;
            const now = Date.now();
            if (code === lastCode && now - lastScannedAt < 800) return;
            lastCode = code;
            lastScannedAt = now;
            codeInput.value = code;

            try {
                const response = await fetch('{{ route('products.lookup') }}?barcode=' + encodeURIComponent(code), {
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Product code lookup failed.');
                message.replaceChildren();

                if (data.exists) {
                    message.append(document.createTextNode('Code already exists: ' + data.product.name + '. '));
                    const editLink = document.createElement('a');
                    editLink.href = data.product.edit_url;
                    editLink.textContent = 'Edit / restock';
                    message.append(editLink);
                    return;
                }

                message.textContent = 'New code. Enter the product details to register this item.';
                nameInput.focus();
            } catch (error) {
                message.textContent = error.message || 'Product code lookup failed.';
            }
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
            if (name === 'NotFoundError') return 'No camera was found. Enter the product code manually.';
            if (name === 'NotReadableError') return 'The camera could not start. Close other apps using it and try again.';
            return detail || 'Camera unavailable. Enter the product code manually.';
        }

        document.getElementById('focus-barcode').addEventListener('click', () => codeInput.focus());
        codeInput.addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            lookup(codeInput.value);
        });
        codeInput.addEventListener('change', () => lookup(codeInput.value));

        document.getElementById('scan-barcode').addEventListener('click', async () => {
            panel.style.display = 'block';
            try {
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                    throw new Error('Camera access requires HTTPS. Open this page using the forwarded HTTPS URL or localhost.');
                }
                if (!window.Html5Qrcode) throw new Error('Camera scanner library did not load.');
                cameraScanner = new Html5Qrcode('product-reader');
                await cameraScanner.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 250, height: 100 },
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
                    async code => {
                        try {
                            await closeScanner();
                            await lookup(code);
                        } catch (error) {
                            message.textContent = error.message || 'Could not stop the camera.';
                        }
                    },
                    () => {},
                );
            } catch (error) {
                message.textContent = cameraErrorMessage(error);
            }
        });

        function stopPhonePolling() {
            if (phonePollTimer) window.clearInterval(phonePollTimer);
            phonePollTimer = null;
            phoneResultUrl = null;
        }

        function closePhoneScanner() {
            stopPhonePolling();
            document.getElementById('phone-scan-panel').hidden = true;
        }

        function updatePhoneQr() {
            const url = document.getElementById('phone-scan-url').value.trim();
            const qr = document.getElementById('phone-scan-qr');
            const link = document.getElementById('phone-scan-link');
            qr.replaceChildren();
            link.href = url || '#';
            link.textContent = url;
            if (url && window.QRCode) new QRCode(qr, { text: url, width: 220, height: 220 });
            else if (url) document.getElementById('phone-scan-message').textContent = 'QR library unavailable. Open the phone URL directly.';
        }

        document.getElementById('scan-with-phone').addEventListener('click', async () => {
            const status = document.getElementById('phone-scan-message');
            status.textContent = 'Preparing a one-time phone scan session...';
            document.getElementById('phone-scan-panel').hidden = false;
            try {
                const response = await fetch('{{ route('products.phone-scan.create') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const session = await response.json();
                if (!response.ok) throw new Error(session.message || 'Could not start the phone scanner.');

                document.getElementById('phone-scan-url').value = session.mobile_url;
                phoneResultUrl = session.result_url;
                updatePhoneQr();
                status.textContent = 'Waiting for a scan. This QR expires in five minutes.';
                phonePollTimer = window.setInterval(async () => {
                    if (phonePollInProgress) return;
                    phonePollInProgress = true;
                    try {
                        const resultResponse = await fetch(phoneResultUrl, {
                            headers: { Accept: 'application/json' },
                        });
                        const result = await resultResponse.json();
                        if (!resultResponse.ok) throw new Error(result.message || 'Phone scan session expired.');
                        if (result.status === 'complete') {
                            window.clearInterval(phonePollTimer);
                            phonePollTimer = null;
                            phoneResultUrl = null;
                            await lookup(result.code);
                            status.textContent = 'Code received from phone and added to the product form.';
                        }
                    } catch (error) {
                        stopPhonePolling();
                        status.textContent = error.message || 'Phone scan session expired. Start a new scan.';
                    } finally {
                        phonePollInProgress = false;
                    }
                }, 1000);
            } catch (error) {
                stopPhonePolling();
                status.textContent = error.message || 'Could not start the phone scanner.';
            }
        });

        document.getElementById('phone-scan-url').addEventListener('input', updatePhoneQr);
        document.getElementById('close-phone-scan').addEventListener('click', closePhoneScanner);
        document.getElementById('stop-product-scanner').addEventListener('click', async () => {
            try {
                await closeScanner();
            } catch (error) {
                message.textContent = error.message || 'Could not stop the camera.';
            }
        });

        document.addEventListener('click', event => {
            if (event.target.closest('input, select, textarea, button[type="submit"], .bluetooth-help')) return;
            codeInput.focus();
        });
        window.addEventListener('pagehide', closePhoneScanner);
    });
</script>
<style>
    .product-code-actions { flex-wrap:wrap; }
    .product-code-actions input { flex:1; min-width:220px; }
    .phone-scan-panel { background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; margin:12px 0; max-width:480px; padding:16px; }
    #phone-scan-url { border:1px solid #94a3b8; border-radius:5px; font:inherit; margin-top:6px; padding:8px; width:100%; }
    #phone-scan-qr { display:flex; justify-content:center; margin:16px; }
    @media(max-width:640px) { .product-code-actions { align-items:stretch; flex-direction:column; } .product-code-actions .button { min-height:44px; } }
</style>
