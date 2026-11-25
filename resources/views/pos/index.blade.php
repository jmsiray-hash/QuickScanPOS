@extends('layouts.app')

@section('title', 'POS Checkout | QuickScanPos')

@section('content')
    <h2>POS Checkout</h2>
    <div id="pos-toast" class="alert" role="status" hidden></div>
    <div class="page-actions pos-actions">
        <div class="row-actions pos-scanner-controls">
            <input id="pos-barcode" type="text" placeholder="Customer CART- code or product barcode" autocomplete="off" autofocus>
            <button class="button button-secondary" id="focus-pos-scanner" type="button">Focus Scanner</button>
            <button class="button" id="add-pos-code" type="button">Add Product / Load Customer Cart</button>
            <button class="button button-secondary" id="scan-item" type="button">Scan Customer QR / Product</button>
        </div>
        <div class="row-actions">
            <a class="button button-secondary" href="{{ route('pos.store-qr') }}">Store Entry QR</a>
            <button class="button" id="generate-verification" type="button">Connect Cashier Phone</button>
        </div>
    </div>

    <p class="cashier-flow-hint">Scan the customer's CART QR here to load their items. To check the physical items with a cashier phone, choose <strong>Connect Cashier Phone</strong> and scan the new QR with that phone. It works online over HTTPS; no Bluetooth pairing is needed.</p>

    <div id="pos-scanner" hidden>
        <div id="pos-reader"></div>
        <button class="button button-secondary" id="stop-pos-scanner" type="button">Close Camera</button>
    </div>
    <p id="pos-cart-source" class="field-hint"></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Product / Code</th><th>Qty</th><th>Price</th><th>Total</th><th></th></tr></thead>
            <tbody id="cart-body"></tbody>
        </table>
    </div>
    <div id="cart-summary"></div>
    <section id="verification-panel" class="verification-panel" hidden>
        <h3>Cashier Phone Item Check</h3>
        <p>Open the phone camera and scan this QR. The phone will show the expected items and let the cashier scan each physical item before payment.</p>
        <div id="verification-qr"></div>
        <p class="field-hint">Keep this cashier page open. The checkout status updates when every expected piece is checked.</p>
        <p id="verification-link"></p>
        <div id="verification-badge" class="verified-banner" hidden>SCANNED AND CHECKED ITEM - READY FOR PAYMENT</div>
    </section>
    <button class="button" id="checkout" type="button">Complete Checkout</button>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const barcodeInput = document.getElementById('pos-barcode');
            const toast = document.getElementById('pos-toast');
            const importUrl = @json(route('pos.import-cart', 'CART_TOKEN'));
            const verificationStatusUrl = @json(route('pos.verify-status', 'VERIFY_CODE'));
            const verificationUrlTemplate = @json(route('pos.verify', 'VERIFY_CODE'));
            const verificationCreateUrl = @json(route('pos.verification.create'));
            let scanner = null;
            let lastCode = '';
            let lastScannedAt = 0;
            let isVerified = false;
            let currentCartToken = null;
            let pollTimer = null;
            let polledStatusUrl = null;

            const money = amount => '₱' + Number(amount).toFixed(2);
            const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);
            function updatePaymentState() {
                const button = document.getElementById('checkout');
                button.textContent = currentCartToken && !isVerified
                    ? 'Verify Items Before Checkout'
                    : isVerified
                    ? 'Complete Checkout - Verified'
                    : 'Complete Checkout';
                button.disabled = Boolean(currentCartToken && !isVerified);
                button.classList.toggle('payment-ready', isVerified);
                button.classList.toggle('payment-warning', Boolean(currentCartToken && !isVerified));
            }
            function show(message, isError = false) {
                toast.textContent = message;
                toast.className = 'alert ' + (isError ? 'alert-errors' : 'alert-success');
                toast.hidden = false;
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
            function focusScanner() {
                const active = document.activeElement;
                if (active && active !== barcodeInput && active.matches('input,select,textarea')) return;
                barcodeInput.focus();
            }
            async function request(url, method = 'GET', data = null) {
                const options = {
                    method,
                    headers: { Accept: 'application/json' },
                };
                if (method !== 'GET') {
                    options.headers['Content-Type'] = 'application/json';
                    options.headers['X-CSRF-TOKEN'] = csrf;
                    options.body = JSON.stringify(data || {});
                }
                const response = await fetch(url, options);
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
                return result;
            }
            function render(cart) {
                const body = document.getElementById('cart-body');
                body.innerHTML = cart.items.length ? cart.items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.name)}<br><small>${escapeHtml(item.barcode)}</small></td>
                        <td><input class="pos-quantity" data-id="${item.product_id}" type="number" min="0" max="${item.available_stock}" value="${item.quantity}" aria-label="Quantity for ${escapeHtml(item.name)}"></td>
                        <td>${money(item.unit_price)}</td>
                        <td>${money(item.quantity * item.unit_price)}</td>
                        <td><button class="button button-secondary pos-remove" data-id="${item.product_id}" type="button">Remove</button></td>
                    </tr>
                `).join('') : '<tr><td colspan="5">Cart is empty. Scan a product or import a CART- code.</td></tr>';
                document.getElementById('cart-summary').innerHTML = `<p><strong>Subtotal:</strong> ${money(cart.subtotal)} &nbsp; <strong>Total:</strong> ${money(cart.total)}</p>`;
                currentCartToken = cart.customer_cart_token || currentCartToken;
                document.querySelectorAll('.pos-quantity').forEach(input => {
                    input.addEventListener('change', () => update(input.dataset.id, Number(input.value)));
                });
                document.querySelectorAll('.pos-remove').forEach(button => {
                    button.addEventListener('click', () => update(button.dataset.id, 0));
                });
                const source = document.getElementById('pos-cart-source');
                source.textContent = currentCartToken ? 'Customer cart: ' + currentCartToken : '';
                updatePaymentState();
                if (cart.verification_code && !pollTimer) {
                    const code = cart.verification_code;
                    const url = verificationStatusUrl.replace('VERIFY_CODE', encodeURIComponent(code));
                    setupVerification(
                        code,
                        verificationUrlTemplate.replace('VERIFY_CODE', encodeURIComponent(code)),
                        url,
                        cart.is_verified,
                    );
                }
            }
            async function scan(rawCode) {
                const code = String(rawCode || '').trim().toUpperCase();
                barcodeInput.value = '';
                const now = Date.now();
                if (!code || (code === lastCode && now - lastScannedAt < 800)) return;
                lastCode = code;
                lastScannedAt = now;
                try {
                    if (code.startsWith('CART-')) {
                        const result = await request(importUrl.replace('CART_TOKEN', encodeURIComponent(code)));
                        currentCartToken = result.customer_cart_token;
                        isVerified = result.is_verified === true;
                        document.getElementById('pos-cart-source').textContent = 'Customer: ' + result.customer_name + ' · ' + result.customer_cart_token;
                        render(result.cart);
                        if (result.verification_code) {
                            setupVerification(
                                result.verification_code,
                                result.verification_url,
                                result.status_url,
                                result.is_verified,
                            );
                        } else {
                            if (pollTimer) window.clearInterval(pollTimer);
                            pollTimer = null;
                            polledStatusUrl = null;
                            isVerified = false;
                            document.getElementById('verification-panel').hidden = true;
                        }
                        show(result.message);
                    } else {
                        const result = await request('{{ route('pos.scan') }}', 'POST', { code });
                        if (currentCartToken) {
                            isVerified = false;
                            document.getElementById('verification-badge').hidden = true;
                        }
                        render(result.cart);
                        show(result.message);
                    }
                    beep();
                } catch (error) {
                    show(error.message, true);
                    beep(true);
                } finally {
                    focusScanner();
                }
            }
            async function update(productId, quantity) {
                try {
                    const result = await request('{{ route('pos.cart.update') }}', 'POST', {
                        product_id: Number(productId),
                        quantity: Number(quantity),
                    });
                    if (currentCartToken) {
                        isVerified = false;
                        document.getElementById('verification-badge').hidden = true;
                    }
                    render(result.cart);
                } catch (error) {
                    show(error.message, true);
                    beep(true);
                } finally {
                    if (document.activeElement?.classList.contains('pos-quantity')) {
                        document.activeElement.blur();
                    }
                    focusScanner();
                }
            }
            function setupVerification(code, url, statusUrl, verified = false) {
                isVerified = verified;
                updatePaymentState();
                const panel = document.getElementById('verification-panel');
                const qr = document.getElementById('verification-qr');
                const link = document.getElementById('verification-link');
                panel.hidden = false;
                qr.replaceChildren();
                if (window.QRCode) new QRCode(qr, { text: url, width: 200, height: 200 });
                link.innerHTML = `<a href="${escapeHtml(url)}" target="_blank" rel="noopener">Open mobile verification</a> · ${escapeHtml(code)}`;
                document.getElementById('verification-badge').hidden = !isVerified;
                if (pollTimer && statusUrl !== polledStatusUrl) {
                    window.clearInterval(pollTimer);
                    pollTimer = null;
                }
                if (statusUrl && !pollTimer) {
                    polledStatusUrl = statusUrl;
                    pollTimer = window.setInterval(async () => {
                        try {
                            const status = await request(statusUrl);
                            isVerified = status.is_verified;
                            document.getElementById('verification-badge').hidden = !isVerified;
                            updatePaymentState();
                            if (isVerified) show('SCANNED AND CHECKED ITEM - READY FOR PAYMENT');
                        } catch (error) {
                            show(error.message, true);
                            window.clearInterval(pollTimer);
                            pollTimer = null;
                            polledStatusUrl = null;
                        }
                    }, 2000);
                }
            }

            document.getElementById('focus-pos-scanner').addEventListener('click', () => barcodeInput.focus());
            document.getElementById('add-pos-code').addEventListener('click', () => scan(barcodeInput.value));
            barcodeInput.addEventListener('keydown', event => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const code = barcodeInput.value;
                barcodeInput.value = '';
                scan(code);
            });
            document.getElementById('scan-item').addEventListener('click', async () => {
                document.getElementById('pos-scanner').hidden = false;
                try {
                    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                        throw new Error('Camera access requires HTTPS. Open this page using the forwarded HTTPS URL or localhost.');
                    }
                    if (!window.Html5Qrcode) throw new Error('Camera scanner library did not load.');
                    scanner = new Html5Qrcode('pos-reader');
                    await scanner.start(
                        { facingMode: 'environment' },
                        {
                            fps: 10,
                            qrbox: { width: 280, height: 120 },
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
                        code => scan(code),
                        () => {},
                    );
                } catch (error) {
                    show(cameraErrorMessage(error), true);
                }
            });
            document.getElementById('stop-pos-scanner').addEventListener('click', async () => {
                if (!scanner) return;
                try {
                    await scanner.stop();
                    scanner.clear();
                    scanner = null;
                    document.getElementById('pos-scanner').hidden = true;
                } catch (error) {
                    show(error.message || 'Could not stop the camera.', true);
                }
            });
            document.getElementById('generate-verification').addEventListener('click', async () => {
                try {
                    const result = await request(verificationCreateUrl, 'POST');
                    currentCartToken = result.customer_cart_token;
                    document.getElementById('pos-cart-source').textContent = 'POS verification cart: ' + currentCartToken;
                    setupVerification(result.verification_code, result.verification_url, result.status_url);
                    show(result.message);
                } catch (error) {
                    show(error.message, true);
                    beep(true);
                } finally {
                    focusScanner();
                }
            });
            document.getElementById('checkout').addEventListener('click', async () => {
                if (currentCartToken && !isVerified) {
                    show('Scan and verify every item with the cashier phone before checkout.', true);
                    return;
                }
                try {
                    const result = await request('{{ route('pos.checkout') }}', 'POST');
                    currentCartToken = null;
                    isVerified = false;
                    if (pollTimer) window.clearInterval(pollTimer);
                    pollTimer = null;
                    polledStatusUrl = null;
                    document.getElementById('verification-panel').hidden = true;
                    render(result.cart);
                    show(result.message + ' Sale #' + result.sale_id);
                    beep();
                } catch (error) {
                    show(error.message, true);
                    beep(true);
                } finally {
                    focusScanner();
                }
            });
            document.addEventListener('click', event => {
                if (event.target.closest('input,select,textarea')) return;
                focusScanner();
            });
            document.addEventListener('pagehide', () => {
                if (pollTimer) window.clearInterval(pollTimer);
            });
            render(@json($cart));
            barcodeInput.focus();
        });
    </script>
    <style>
        .pos-actions { align-items:flex-start; flex-direction:column; gap:14px; }
        .pos-scanner-controls { flex-wrap:wrap; width:100%; }
        .pos-scanner-controls input { flex:1; min-width:230px; border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:10px; }
        .cashier-flow-hint { background:#f0fdfa; border-left:4px solid #0f766e; margin:16px 0; padding:12px 16px; }
        .pos-quantity { width:72px; border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:6px; }
        .verification-panel { background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; margin:20px 0; padding:20px; }
        #checkout.payment-ready { background:#15803d; }
        #checkout.payment-warning { background:#b45309; }
        #verification-qr { display:flex; justify-content:center; margin:16px; }
        .verified-banner { background:#dcfce7; color:#166534; font-weight:800; padding:16px; text-align:center; }
        #pos-reader { max-width:520px; }
        #checkout:disabled { background:#94a3b8; cursor:not-allowed; }
        @media(max-width:640px) { .pos-scanner-controls { align-items:stretch; flex-direction:column; } .pos-scanner-controls .button { min-height:44px; } }
    </style>
@endsection
