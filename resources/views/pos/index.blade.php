@extends('layouts.app')

@section('title', 'POS Checkout | QuickScanPos')

@section('content')
    <h2>POS Checkout</h2>
    @if (!$activeShift)
        <section class="shift-opening-panel">
            <h3>Open Shift</h3>
            <p>Record the opening cash fund before completing transactions.</p>
            <form method="POST" action="{{ route('pos.shift.open') }}" class="row-actions">
                @csrf
                <label for="opening-cash">Opening cash fund (₱)</label>
                <input id="opening-cash" name="opening_cash" type="number" step="0.01" min="0" required>
                <button class="button" type="submit">Open Shift</button>
                @error('opening_cash')<span class="error">{{ $message }}</span>@enderror
            </form>
        </section>
    @else
        <p class="active-shift-note">Shift open since {{ $activeShift->opened_at->format('M j, g:i A') }} · Opening fund ₱{{ number_format($activeShift->opening_cash, 2) }}</p>
    @endif
    <div id="pos-toast" class="alert" role="status" hidden></div>
    <div class="page-actions pos-actions">
        <div class="row-actions pos-scanner-controls">
            <input id="pos-barcode" type="text" placeholder="Customer CART- code or product barcode" autocomplete="off" autofocus>
            <button class="button button-secondary" id="focus-pos-scanner" type="button">Focus Scanner</button>
            <button class="button" id="add-pos-code" type="button">Add Product / Load Customer Cart</button>
            <button class="button" id="scan-customer-qr" type="button">Scan Customer QR</button>
            <button class="button button-secondary" id="scan-item" type="button">Scan Product Barcode</button>
        </div>
        <div class="row-actions">
            <a class="button button-secondary" href="{{ route('pos.store-qr') }}">Store Entry QR</a>
            <button class="button" id="generate-verification" type="button">Connect Cashier Phone</button>
        </div>
    </div>

    <p class="cashier-flow-hint">Scan the customer's final checkout QR to load their items. You can proceed to checkout immediately; cashier-phone item verification is optional and can be used as an extra check when needed. Use <strong>Connect Cashier Phone</strong> to create a verification QR. It works online over HTTPS; no Bluetooth pairing is needed.</p>

    <section class="customer-discount-panel">
        <h3>Customer &amp; Discounts</h3>
        <div class="row-actions">
            <input id="customer-search" type="search" placeholder="Find customer by name, email, or phone" autocomplete="off">
            <button class="button button-secondary" id="find-customer" type="button">Find Customer</button>
            <select id="customer-results" aria-label="Customer search results" hidden></select>
            <button class="button button-secondary" id="clear-customer" type="button">Clear Customer</button>
        </div>
        <p id="customer-discount-info" class="field-hint">No customer selected. Item discounts require a customer record.</p>
        <p id="customer-purchase-link" class="field-hint"></p>
    </section>

    <div id="pos-scanner" hidden>
        <div id="pos-reader"></div>
        <p id="pos-scan-mode" class="field-hint">Point the camera at a code.</p>
        <button class="button button-secondary" id="stop-pos-scanner" type="button">Close Camera</button>
    </div>
    <div id="customer-scan-success" class="customer-scan-success" role="status" aria-live="polite" hidden>
        <span class="customer-scan-check" aria-hidden="true">✓</span>
        <span><strong>SCANNED</strong><small>Proceed to payment when ready.</small></span>
    </div>
    <p id="pos-cart-source" class="field-hint"></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Product / Code</th><th>Qty</th><th>Price</th><th>Item discount</th><th>Total</th><th></th></tr></thead>
            <tbody id="cart-body"></tbody>
        </table>
    </div>
    <div id="cart-summary"></div>
    <section class="payment-panel">
        <h3>Payment &amp; Discounts</h3>
        <div class="payment-fields">
            <div class="field">
                <label for="payment-method">Payment method</label>
                <select id="payment-method">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="card">Card</option>
                </select>
            </div>
            <div class="field" id="cash-received-field">
                <label for="cash-received">Cash received (₱)</label>
                <input id="cash-received" type="number" min="0" step="0.01" inputmode="decimal">
                <small id="change-preview">Change: ₱0.00</small>
            </div>
            <div class="field" id="payment-reference-field" hidden>
                <label for="payment-reference">Reference number</label>
                <input id="payment-reference" maxlength="100" autocomplete="off">
            </div>
        </div>
        <div class="payment-fields special-discount-fields">
            <div class="field">
                <label for="special-discount-type">Senior/PWD discount</label>
                <select id="special-discount-type">
                    <option value="">None</option>
                    <option value="senior">Senior Citizen (20%)</option>
                    <option value="pwd">PWD (20%)</option>
                </select>
            </div>
            <div class="field" id="special-discount-id-field" hidden>
                <label for="special-discount-id">Senior/PWD ID number</label>
                <input id="special-discount-id" maxlength="100" autocomplete="off">
            </div>
        </div>
        <p class="field-hint">The 20% statutory discount and VAT exemption apply only to products marked eligible in the catalog. Prices are treated as VAT-inclusive when POS_VAT_RATE is above zero; current rate: {{ number_format($vatRate, 2) }}%.</p>
    </section>
    <section id="verification-panel" class="verification-panel" hidden>
        <h3>Cashier Phone Item Check</h3>
        <p>Optional item check: open the cashier phone camera and scan this QR to compare the physical items with the customer's cart.</p>
        <div id="verification-qr"></div>
        <p class="field-hint">You may complete checkout without this check. If you choose to verify, this page shows when all expected pieces are checked.</p>
        <p id="verification-link"></p>
        <div id="verification-badge" class="verified-banner" hidden>SCANNED AND CHECKED ITEM - READY FOR PAYMENT</div>
    </section>
    <button class="button" id="checkout" type="button" @disabled(!$activeShift)>Complete Sale</button>
    <dialog id="sale-receipt-modal" class="sale-receipt-modal" aria-labelledby="sale-receipt-title">
        <div class="receipt-modal-actions">
            <h2 id="sale-receipt-title">QuickScanPos Receipt</h2>
            <button class="button button-secondary" id="close-sale-receipt" type="button">Close</button>
        </div>
        <div id="sale-receipt-content"></div>
        <div class="receipt-modal-actions">
            <a id="full-receipt-link" class="button button-secondary" href="#">View full receipt</a>
            <button class="button" id="print-sale-receipt" type="button">Print Receipt</button>
        </div>
    </dialog>

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
            const customerLookupUrl = @json(route('customers.lookup'));
            const customerSelectUrl = @json(route('pos.customer.select'));
            const hasOpenShift = @json($activeShift !== null);
            const vatRate = @json($vatRate);
            let currentCart = null;
            let isSubmittingSale = false;
            let scanner = null;
            let scanInProgress = false;
            let lastCode = '';
            let lastScannedAt = 0;
            let isVerified = false;
            let currentCartToken = null;
            let currentCustomerName = null;
            let pollTimer = null;
            let polledStatusUrl = null;
            let selectedCustomerId = null;
            let discountIdType = null;

            const money = amount => '₱' + Number(amount).toFixed(2);
            const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);
            function updatePaymentState() {
                const button = document.getElementById('checkout');
                button.textContent = isVerified
                    ? 'Complete Sale - Phone Check Complete'
                    : 'Complete Sale';
                button.disabled = !hasOpenShift || !currentCart?.items?.length || isSubmittingSale;
                button.classList.toggle('payment-ready', isVerified);
                button.classList.remove('payment-warning');
                updatePaymentPreview();
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
                currentCart = cart;
                const body = document.getElementById('cart-body');
                body.innerHTML = cart.items.length ? cart.items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.name)} ${item.is_promo ? '<span class="promo-tag">PROMO</span>' : ''}<br><small>${escapeHtml(item.barcode)}</small></td>
                        <td><input class="pos-quantity" data-id="${item.product_id}" type="number" min="0" max="${item.available_stock}" value="${item.quantity}" aria-label="Quantity for ${escapeHtml(item.name)}"></td>
                        <td>${money(item.unit_price)}</td>
                        <td class="pos-discount-fields">
                            <select class="pos-discount-type" data-id="${item.product_id}" aria-label="Discount type for ${escapeHtml(item.name)}" ${cart.customer_id ? '' : 'disabled'}>
                                <option value="" ${!item.discount_type ? 'selected' : ''}>None</option>
                                <option value="store" ${item.discount_type === 'store' ? 'selected' : ''}>Store / loyalty</option>
                            </select>
                            <label class="sr-only" for="discount-rate-${item.product_id}">Discount percent</label>
                            <input id="discount-rate-${item.product_id}" class="pos-discount-percent" data-id="${item.product_id}" type="number" min="0" max="100" step="0.01" value="${Number(item.discount_percent || 0)}" ${cart.customer_id && item.discount_type ? '' : 'disabled'} aria-label="Discount percent for ${escapeHtml(item.name)}">
                            <label class="promo-label" for="promo-price-${item.product_id}">Promo price</label>
                            <input id="promo-price-${item.product_id}" class="pos-promo-price" data-id="${item.product_id}" type="number" min="0" step="0.01" value="${item.promo_price ?? ''}" placeholder="Regular: ${money(item.unit_price)}" aria-label="Promo price for ${escapeHtml(item.name)}">
                            ${item.senior_pwd_eligible ? '<small class="eligibility-note">Senior/PWD eligible</small>' : ''}
                            ${Number(item.discount_amount || 0) ? `<small>Discount: -${money(item.discount_amount)}</small>` : ''}
                        </td>
                        <td>${money(item.line_total ?? item.quantity * item.unit_price)}</td>
                        <td><button class="button button-secondary pos-remove" data-id="${item.product_id}" type="button">Remove</button></td>
                    </tr>
                `).join('') : '<tr><td colspan="6">Cart is empty. Scan a product or import a CART- code.</td></tr>';
                document.getElementById('cart-summary').innerHTML = `<p><strong>Subtotal:</strong> ${money(cart.subtotal)} &nbsp; <strong>Discount:</strong> ${money(cart.discount)} &nbsp; <strong>VAT / Tax:</strong> ${money(cart.tax)} &nbsp; <strong>Total:</strong> ${money(cart.total)}</p>`;
                currentCartToken = cart.customer_cart_token || currentCartToken;
                currentCustomerName = cart.customer_name || currentCustomerName;
                selectedCustomerId = cart.customer_id || null;
                discountIdType = cart.customer_discount_id_type || null;
                const customerInfo = document.getElementById('customer-discount-info');
                const customerHistory = document.getElementById('customer-purchase-link');
                if (selectedCustomerId) {
                    const idDetails = cart.customer_discount_id_type
                        ? ` · ${String(cart.customer_discount_id_type).toUpperCase()} ID: ${escapeHtml(cart.customer_discount_id_number || 'number not recorded')}`
                        : '';
                    const reminder = cart.customer_store_discount_reminder
                        ? ' · Reminder: customer usually asks for a store discount'
                        : '';
                    customerInfo.innerHTML = `<strong>${escapeHtml(currentCustomerName || 'Selected customer')}</strong>${idDetails}${reminder}<br>Monthly store-discount quota: ${money(cart.monthly_discount_used || 0)} used of ${money(cart.monthly_discount_limit || 0)} · ${money(cart.monthly_discount_remaining || 0)} remaining`;
                    customerHistory.innerHTML = `<a href="${@json(route('customers.index'))}/${encodeURIComponent(selectedCustomerId)}">Open customer record and purchase history</a>`;
                } else {
                    customerInfo.textContent = 'No customer selected. Item discounts require a customer record.';
                    customerHistory.textContent = '';
                }
                if (Object.prototype.hasOwnProperty.call(cart, 'is_verified')) {
                    isVerified = cart.is_verified === true;
                }
                document.querySelectorAll('.pos-quantity').forEach(input => {
                    input.addEventListener('change', () => updateFromRow(input));
                });
                document.querySelectorAll('.pos-discount-type, .pos-discount-percent').forEach(input => {
                    input.addEventListener('change', () => updateFromRow(input));
                });
                document.querySelectorAll('.pos-promo-price').forEach(input => {
                    input.addEventListener('change', () => updateFromRow(input));
                });
                document.querySelectorAll('.pos-remove').forEach(button => {
                    button.addEventListener('click', () => update(button.dataset.id, 0));
                });
                const source = document.getElementById('pos-cart-source');
                source.textContent = currentCartToken
                    ? (currentCustomerName ? 'Customer: ' + currentCustomerName + ' · ' : 'Customer cart: ') + currentCartToken
                    : '';
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
            function cartReferenceFromScan(rawCode) {
                const raw = String(rawCode || '').trim();
                const normalized = raw.toUpperCase();
                if (normalized.startsWith('CART-')) return { cartToken: normalized, verificationCode: null };
                if (normalized.startsWith('VERIFY-')) return { cartToken: null, verificationCode: normalized };

                try {
                    const url = new URL(raw);
                    const candidates = [
                        url.searchParams.get('cart'),
                        url.searchParams.get('token'),
                        ...url.pathname.split('/').filter(Boolean).reverse(),
                    ];
                    const values = candidates
                        .map(value => String(value || '').trim().toUpperCase())
                        .filter(Boolean);
                    return {
                        cartToken: values.find(value => value.startsWith('CART-')) || null,
                        verificationCode: values.find(value => value.startsWith('VERIFY-')) || null,
                    };
                } catch (error) {
                    return { cartToken: null, verificationCode: null };
                }
            }
            async function scan(rawCode) {
                const reference = cartReferenceFromScan(rawCode);
                const cartToken = reference.cartToken;
                const verificationCode = reference.verificationCode;
                const code = cartToken || verificationCode || String(rawCode || '').trim().toUpperCase();
                barcodeInput.value = '';
                const now = Date.now();
                if (!code || scanInProgress || (code === lastCode && now - lastScannedAt < 1200)) return;
                lastCode = code;
                lastScannedAt = now;
                scanInProgress = true;
                try {
                    if (cartToken || verificationCode) {
                        const result = cartToken
                            ? await request(importUrl.replace('CART_TOKEN', encodeURIComponent(cartToken)))
                            : await request('{{ route('pos.scan') }}', 'POST', { code: verificationCode });
                        const cameraStopped = await stopCamera();
                        const scanSuccess = document.getElementById('customer-scan-success');
                        scanSuccess.hidden = false;
                        scanSuccess.classList.remove('customer-scan-success-animate');
                        void scanSuccess.offsetWidth;
                        scanSuccess.classList.add('customer-scan-success-animate');
                        currentCartToken = result.customer_cart_token;
                        currentCustomerName = result.customer_name || null;
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
                        show(cameraStopped
                            ? 'Customer QR scanned. Proceed to payment when ready.'
                            : 'Customer QR scanned. Proceed to payment; close the camera manually.');
                    } else {
                        document.getElementById('customer-scan-success').hidden = true;
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
                    scanInProgress = false;
                    focusScanner();
                }
            }
            async function stopCamera() {
                if (!scanner) {
                    document.getElementById('pos-scanner').hidden = true;
                    document.getElementById('pos-scan-mode').textContent = '';
                    return true;
                }

                const activeScanner = scanner;
                try {
                    await activeScanner.stop();
                    activeScanner.clear();
                    scanner = null;
                    document.getElementById('pos-scanner').hidden = true;
                    document.getElementById('pos-scan-mode').textContent = '';
                    return true;
                } catch (error) {
                    show(error.message || 'Could not stop the camera.', true);
                    return false;
                }
            }
            function updateFromRow(input) {
                const row = input.closest('tr');
                update(
                    input.dataset.id,
                    Number(row.querySelector('.pos-quantity').value),
                    row.querySelector('.pos-discount-type').value || null,
                    Number(row.querySelector('.pos-discount-percent').value || 0),
                    row.querySelector('.pos-promo-price').value === '' ? null : Number(row.querySelector('.pos-promo-price').value),
                );
            }
            async function update(productId, quantity, discountType = null, discountPercent = 0, promoPrice = null) {
                try {
                    const result = await request('{{ route('pos.cart.update') }}', 'POST', {
                        product_id: Number(productId),
                        quantity: Number(quantity),
                        discount_type: discountType,
                        discount_percent: Number(discountPercent),
                        promo_price: promoPrice,
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
            function checkoutTotal() {
                if (!currentCart) return 0;
                let total = Number(currentCart.total || 0);
                const specialType = document.getElementById('special-discount-type').value;
                if (specialType) {
                    const divider = 1 + Number(vatRate || 0) / 100;
                    for (const item of currentCart.items) {
                        if (!item.senior_pwd_eligible || item.is_promo) continue;
                        const base = Number(item.unit_price) * Number(item.quantity) / divider;
                        const specialTotal = Math.round(base * (1 - 0.20) * 100) / 100;
                        total -= Number(item.line_total) - specialTotal;
                    }
                }
                return Math.max(0, Math.round(total * 100) / 100);
            }
            function updatePaymentPreview() {
                const method = document.getElementById('payment-method').value;
                const cashField = document.getElementById('cash-received-field');
                const referenceField = document.getElementById('payment-reference-field');
                const specialType = document.getElementById('special-discount-type').value;
                const idField = document.getElementById('special-discount-id-field');
                cashField.hidden = method !== 'cash';
                referenceField.hidden = !['gcash', 'maya'].includes(method);
                idField.hidden = !specialType;
                const cashReceived = Number(document.getElementById('cash-received').value || 0);
                const total = checkoutTotal();
                document.getElementById('change-preview').textContent =
                    'Change: ₱' + Math.max(0, cashReceived - total).toFixed(2) + ' · Total due: ₱' + total.toFixed(2);
                document.getElementById('payment-reference').required = ['gcash', 'maya'].includes(method);
                document.getElementById('special-discount-id').required = Boolean(specialType);
                const paymentReady = method !== 'cash'
                    ? (!['gcash', 'maya'].includes(method) || document.getElementById('payment-reference').value.trim() !== '')
                    : cashReceived >= total && total > 0;
                const specialIdReady = !specialType || document.getElementById('special-discount-id').value.trim() !== '';
                document.getElementById('checkout').disabled = !hasOpenShift || !currentCart?.items?.length || !paymentReady || !specialIdReady || isSubmittingSale;
            }
            function renderSaleReceipt(receipt, receiptUrl) {
                const escape = value => escapeHtml(value ?? '');
                const items = receipt.items.map(item => `
                    <tr>
                        <td>${escape(item.name)}${item.is_promo ? ' <strong class="promo-tag">PROMO</strong>' : ''}</td>
                        <td>${item.quantity}</td>
                        <td>${money(item.promo_unit_price ?? item.unit_price)}</td>
                        <td>${money(item.discount_amount)}</td>
                        <td>${money(item.line_total)}</td>
                    </tr>
                `).join('');
                document.getElementById('sale-receipt-content').innerHTML = `
                    <div class="receipt-store"><strong>${escape(receipt.store)}</strong><div>Sales Receipt #${receipt.id}</div></div>
                    <p>${escape(receipt.created_at)}<br>Cashier: ${escape(receipt.cashier)}${receipt.customer_name ? '<br>Customer: ' + escape(receipt.customer_name) : ''}</p>
                    <table class="receipt-modal-table"><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Discount</th><th>Total</th></tr></thead><tbody>${items}</tbody></table>
                    <dl class="receipt-modal-totals">
                        <div><dt>Subtotal</dt><dd>${money(receipt.subtotal)}</dd></div>
                        <div><dt>Discount</dt><dd>-${money(receipt.discount)}</dd></div>
                        <div><dt>VAT / Tax</dt><dd>${money(receipt.tax)}</dd></div>
                        <div class="receipt-grand-total"><dt>Total</dt><dd>${money(receipt.total)}</dd></div>
                        <div><dt>Payment</dt><dd>${escape(String(receipt.payment_method).toUpperCase())}</dd></div>
                        ${receipt.payment_reference ? `<div><dt>Reference</dt><dd>${escape(receipt.payment_reference)}</dd></div>` : ''}
                        ${receipt.special_discount_type ? `<div><dt>${escape(String(receipt.special_discount_type).toUpperCase())} ID</dt><dd>${escape(receipt.special_discount_id_number)}</dd></div>` : ''}
                        ${receipt.cash_received !== null ? `<div><dt>Cash received</dt><dd>${money(receipt.cash_received)}</dd></div><div><dt>Change</dt><dd>${money(receipt.change_due)}</dd></div>` : ''}
                    </dl>`;
                document.getElementById('full-receipt-link').href = receiptUrl;
                document.getElementById('sale-receipt-modal').showModal();
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
            document.getElementById('find-customer').addEventListener('click', async () => {
                const search = document.getElementById('customer-search').value.trim();
                if (!search) {
                    show('Enter a customer name, email, or phone number.', true);
                    return;
                }
                try {
                    const result = await request(customerLookupUrl + '?search=' + encodeURIComponent(search));
                    const select = document.getElementById('customer-results');
                    select.innerHTML = '<option value="">Select a customer...</option>' + result.customers.map(customer =>
                        `<option value="${customer.id}">${escapeHtml(customer.full_name)} · ${escapeHtml(customer.email)}</option>`
                    ).join('');
                    select.hidden = false;
                    if (result.customers.length === 0) show('No matching customer account was found.', true);
                } catch (error) {
                    show(error.message, true);
                }
            });
            document.getElementById('customer-results').addEventListener('change', async event => {
                if (!event.target.value) return;
                try {
                    const result = await request(customerSelectUrl, 'POST', {
                        customer_id: Number(event.target.value),
                    });
                    render(result.cart);
                    show(result.message);
                } catch (error) {
                    show(error.message, true);
                }
            });
            document.getElementById('clear-customer').addEventListener('click', async () => {
                try {
                    const result = await request(customerSelectUrl, 'POST', { customer_id: null });
                    render(result.cart);
                    document.getElementById('customer-results').hidden = true;
                    document.getElementById('customer-results').value = '';
                    show(result.message);
                } catch (error) {
                    show(error.message, true);
                }
            });
            document.getElementById('add-pos-code').addEventListener('click', () => scan(barcodeInput.value));
            barcodeInput.addEventListener('keydown', event => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const code = barcodeInput.value;
                barcodeInput.value = '';
                scan(code);
            });
            async function startCamera(formats, modeMessage) {
                document.getElementById('pos-scanner').hidden = false;
                document.getElementById('pos-scan-mode').textContent = modeMessage;
                try {
                    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                        throw new Error('Camera access requires HTTPS. Open this page using the forwarded HTTPS URL or localhost.');
                    }
                    if (!window.Html5Qrcode) throw new Error('Camera scanner library did not load.');
                    if (scanner) {
                        await scanner.stop();
                        scanner.clear();
                        scanner = null;
                    }
                    scanner = new Html5Qrcode('pos-reader');
                    await scanner.start(
                        { facingMode: 'environment' },
                        {
                            fps: 15,
                            qrbox: (viewfinderWidth, viewfinderHeight) => {
                                const isQrOnly = formats.length === 1
                                    && formats[0] === Html5QrcodeSupportedFormats.QR_CODE;
                                const width = Math.min(Math.floor(viewfinderWidth * 0.9), 720);
                                if (isQrOnly) {
                                    const size = Math.min(width, Math.floor(viewfinderHeight * 0.75));
                                    return { width: size, height: size };
                                }
                                return {
                                    width,
                                    height: Math.min(Math.floor(viewfinderHeight * 0.42), Math.floor(width * 0.48)),
                                };
                            },
                            formatsToSupport: formats,
                        },
                        code => scan(code),
                        () => {},
                    );
                } catch (error) {
                    show(cameraErrorMessage(error), true);
                }
            }
            document.getElementById('scan-customer-qr').addEventListener('click', () => startCamera(
                [Html5QrcodeSupportedFormats.QR_CODE],
                'Point the camera at the customer QR to load the order on this cashier screen.',
            ));
            document.getElementById('scan-item').addEventListener('click', () => startCamera(
                [
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.QR_CODE,
                ],
                'Point the camera at a product barcode or QR code.',
            ));
            document.getElementById('stop-pos-scanner').addEventListener('click', async () => {
                await stopCamera();
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
            document.getElementById('payment-method').addEventListener('change', updatePaymentPreview);
            document.getElementById('cash-received').addEventListener('input', updatePaymentPreview);
            document.getElementById('payment-reference').addEventListener('input', updatePaymentPreview);
            document.getElementById('special-discount-type').addEventListener('change', updatePaymentPreview);
            document.getElementById('special-discount-id').addEventListener('input', updatePaymentPreview);
            document.getElementById('close-sale-receipt').addEventListener('click', () => {
                document.getElementById('sale-receipt-modal').close();
                focusScanner();
            });
            document.getElementById('print-sale-receipt').addEventListener('click', () => window.print());
            document.getElementById('checkout').addEventListener('click', async () => {
                if (isSubmittingSale) return;
                isSubmittingSale = true;
                updatePaymentPreview();
                try {
                    const result = await request('{{ route('pos.checkout') }}', 'POST', {
                        payment_method: document.getElementById('payment-method').value,
                        cash_received: document.getElementById('cash-received').value || null,
                        payment_reference: document.getElementById('payment-reference').value.trim() || null,
                        special_discount_type: document.getElementById('special-discount-type').value || null,
                        special_discount_id_number: document.getElementById('special-discount-id').value.trim() || null,
                    });
                    currentCartToken = null;
                    currentCustomerName = null;
                    isVerified = false;
                    if (pollTimer) window.clearInterval(pollTimer);
                    pollTimer = null;
                    polledStatusUrl = null;
                    document.getElementById('verification-panel').hidden = true;
                    document.getElementById('customer-scan-success').hidden = true;
                    render(result.cart);
                    show(result.message + ' Sale #' + result.sale_id);
                    const receiptLink = document.createElement('a');
                    receiptLink.href = result.receipt_url;
                    receiptLink.textContent = ' View / Print Receipt';
                    receiptLink.style.marginLeft = '8px';
                    toast.append(receiptLink);
                    renderSaleReceipt(result.receipt, result.receipt_url);
                    document.getElementById('cash-received').value = '';
                    document.getElementById('payment-reference').value = '';
                    document.getElementById('special-discount-type').value = '';
                    document.getElementById('special-discount-id').value = '';
                    beep();
                } catch (error) {
                    show(error.message, true);
                    beep(true);
                } finally {
                    isSubmittingSale = false;
                    updatePaymentPreview();
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
        .shift-opening-panel,.payment-panel { background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; margin:16px 0; padding:16px; }
        .shift-opening-panel h3,.payment-panel h3 { margin-top:0; }
        .shift-opening-panel input,.payment-panel input,.payment-panel select { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:9px; }
        .active-shift-note { background:#dcfce7; border-radius:6px; color:#166534; padding:8px 12px; }
        .payment-fields { display:grid; gap:14px; grid-template-columns:repeat(3,minmax(0,1fr)); margin:12px 0; }
        .payment-fields .field { display:grid; gap:5px; }
        .special-discount-fields { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .promo-label,.eligibility-note { color:#64748b; font-size:.75rem; }
        .promo-tag { background:#fef3c7; border-radius:999px; color:#92400e; display:inline-block; font-size:.68rem; font-weight:800; padding:2px 6px; }
        .sale-receipt-modal { border:1px solid #dbe5e3; border-radius:10px; max-height:90vh; max-width:760px; overflow:auto; padding:24px; width:calc(100% - 32px); }
        .sale-receipt-modal::backdrop { background:rgba(15,23,42,.55); }
        .receipt-modal-actions { align-items:center; display:flex; gap:10px; justify-content:space-between; }
        .receipt-modal-actions h2 { margin:0 0 16px; }
        .receipt-store { text-align:center; }
        .receipt-modal-table { font-size:.9rem; min-width:540px; }
        .receipt-modal-table th,.receipt-modal-table td { padding:8px; }
        .receipt-modal-totals { margin-left:auto; max-width:360px; }
        .receipt-modal-totals div { display:flex; justify-content:space-between; gap:20px; }
        .receipt-modal-totals dt,.receipt-modal-totals dd { margin:4px 0; }
        .receipt-modal-totals dd { font-weight:700; }
        .receipt-grand-total { border-top:1px solid #94a3b8; color:#115e59; font-size:1.15rem; margin-top:6px !important; padding-top:6px; }
        .customer-scan-success { align-items:center; background:#dcfce7; border:1px solid #86efac; border-radius:10px; color:#166534; display:flex; gap:14px; margin:16px 0; padding:16px 20px; }
        .customer-scan-success[hidden] { display:none; }
        .customer-scan-check { align-items:center; background:#16a34a; border-radius:50%; color:#fff; display:flex; flex:none; font-size:1.6rem; font-weight:800; height:44px; justify-content:center; width:44px; }
        .customer-scan-success strong,.customer-scan-success small { display:block; }
        .customer-scan-success strong { font-size:1.1rem; letter-spacing:.04em; }
        .customer-scan-success-animate { animation:customer-scan-confirm .65s ease-out; }
        .customer-scan-success-animate .customer-scan-check { animation:customer-scan-check .5s ease-out; }
        @keyframes customer-scan-confirm { from { opacity:.4; transform:translateY(8px) scale(.98); } to { opacity:1; transform:translateY(0) scale(1); } }
        @keyframes customer-scan-check { 0% { transform:scale(.5) rotate(-25deg); } 75% { transform:scale(1.18) rotate(8deg); } 100% { transform:scale(1) rotate(0); } }
        .pos-quantity { width:72px; border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:6px; }
        .pos-discount-fields { min-width:150px; }
        .pos-discount-fields select,.pos-discount-fields input { display:block; margin:3px 0; max-width:150px; }
        .customer-discount-panel { background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; margin:20px 0; padding:16px; }
        .customer-discount-panel input,.customer-discount-panel select { border:1px solid #94a3b8; border-radius:5px; font:inherit; max-width:360px; padding:9px; }
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        .verification-panel { background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; margin:20px 0; padding:20px; }
        #checkout.payment-ready { background:#15803d; }
        #checkout.payment-warning { background:#b45309; }
        #verification-qr { display:flex; justify-content:center; margin:16px; }
        .verified-banner { background:#dcfce7; color:#166534; font-weight:800; padding:16px; text-align:center; }
        #pos-reader { max-width:960px; width:100%; }
        #pos-reader video { height:auto !important; max-width:100% !important; width:100% !important; }
        #checkout:disabled { background:#94a3b8; cursor:not-allowed; }
        @media print {
            body * { visibility:hidden !important; }
            #sale-receipt-modal,#sale-receipt-modal * { visibility:visible !important; }
            #sale-receipt-modal { border:0; inset:0; max-height:none; max-width:none; overflow:visible; position:absolute; width:100%; }
            .receipt-modal-actions { display:none !important; }
        }
        @media(max-width:640px) { .pos-scanner-controls { align-items:stretch; flex-direction:column; } .pos-scanner-controls .button { min-height:44px; } .payment-fields,.special-discount-fields { grid-template-columns:1fr; } }
    </style>
@endsection
