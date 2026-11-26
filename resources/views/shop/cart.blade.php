@extends('layouts.app')

@section('title', 'Your Shopping Cart | QuickScanPos')

@section('content')
    <section class="shop-cart">
        <div class="page-actions">
            <div>
                <h2>Scan &amp; Go Cart</h2>
                <p>Welcome, <strong>{{ $cart->customer_name }}</strong></p>
            </div>
            <span class="cart-token">{{ $cart->token }}</span>
        </div>

        <div id="shop-message" class="alert" role="status" hidden></div>
        @include('components.code-scanner')

        <div class="table-wrap">
            <table>
                <thead><tr><th>Product / Code</th><th>Price</th><th>Quantity</th><th>Total</th><th></th></tr></thead>
                <tbody id="shop-cart-items"></tbody>
            </table>
        </div>
        <div id="shop-summary" class="cart-summary"></div>
        <button id="finish-shopping" class="button" type="button" @disabled($cart->status !== 'shopping')>
            Finish &amp; Generate Cashier QR
        </button>
        <section id="cashier-qr-panel" class="qr-panel" hidden>
            <h3>Show this QR code to the cashier</h3>
            <div id="cashier-cart-qr" aria-label="Cashier cart QR code"></div>
            <p>Cart: <strong id="cashier-cart-token"></strong></p>
            <p>The same QR works on the cashier POS to load your cart and on the cashier phone to open the item scanner.</p>
            <p>Keep this page open until checkout is complete.</p>
        </section>
        <section id="paid-panel" class="qr-panel" hidden>
            <h3>Transaction Complete</h3>
            <p>Your checkout is complete. Thank you for shopping at QuickScanPos.</p>
            <div id="rating-form-wrap">
                <p><strong>Rate our service</strong></p>
                <div class="rating-stars" role="radiogroup" aria-label="Service rating">
                    @for ($i = 1; $i <= 5; $i++)
                        <label class="rating-star"><input type="radio" name="service_rating" value="{{ $i }}"> {{ $i }}★</label>
                    @endfor
                </div>
                <label for="service-feedback">Feedback (optional)</label>
                <textarea id="service-feedback" rows="3" maxlength="1000" placeholder="Tell us about your experience"></textarea>
                <button id="submit-rating" class="button" type="button">Submit Rating</button>
            </div>
            <p id="rating-thanks" hidden></p>
        </section>
    </section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const initialCart = @json($cartData);
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const scanUrl = @json(route('shop.cart.scan', $cart->token));
            const finalizeUrl = @json(route('shop.cart.finalize', $cart->token));
            const rateUrl = @json(route('shop.cart.rate', $cart->token));
            const statusUrl = @json(route('shop.cart.status', $cart->token));
            const itemUrl = @json(route('shop.cart.item.update', ['token' => $cart->token, 'item' => 'ITEM_ID']));
            const body = document.getElementById('shop-cart-items');
            const summary = document.getElementById('shop-summary');
            const message = document.getElementById('shop-message');
            const finish = document.getElementById('finish-shopping');
            const paidPanel = document.getElementById('paid-panel');
            const ratingFormWrap = document.getElementById('rating-form-wrap');
            const ratingThanks = document.getElementById('rating-thanks');
            let cart = initialCart;
            let pollTimer = null;

            const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);
            const money = value => '₱' + Number(value).toFixed(2);
            const showMessage = (text, isError = false) => {
                message.textContent = text;
                message.className = 'alert ' + (isError ? 'alert-errors' : 'alert-success');
                message.hidden = false;
            };
            const send = async (url, method, data = {}) => {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: method === 'GET' ? undefined : JSON.stringify(data),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
                return result;
            };

            const schedulePoll = () => {
                if (pollTimer) window.clearTimeout(pollTimer);
                if (!['ready_for_checkout', 'verified_ready'].includes(cart.status)) return;
                pollTimer = window.setTimeout(async () => {
                    try {
                        const result = await send(statusUrl, 'GET');
                        if (result.cart) {
                            cart = result.cart;
                            render();
                        }
                    } catch (error) {
                        schedulePoll();
                    }
                }, 5000);
            };

            function renderRating() {
                const paid = cart.status === 'paid';
                paidPanel.hidden = !paid;
                finish.hidden = paid;
                if (!paid) return;
                if (cart.rated_at) {
                    ratingFormWrap.hidden = true;
                    ratingThanks.hidden = false;
                    ratingThanks.textContent = `Thank you! You rated our service ${cart.service_rating}/5.`;
                } else {
                    ratingFormWrap.hidden = false;
                    ratingThanks.hidden = true;
                }
            }

            function render() {
                const editable = cart.status === 'shopping';
                body.innerHTML = cart.items.length ? cart.items.map(item => `
                    <tr>
                        <td data-label="Product">
                            <div class="shop-cart-product">
                                ${item.image_url ? `<img class="shop-product-image" src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}" onerror="this.onerror=null;this.outerHTML='<span class=\\'shop-product-image-placeholder\\'>No photo</span>';">` : '<span class="shop-product-image-placeholder">No photo</span>'}
                                <span>${escapeHtml(item.name)}<br><small>${escapeHtml(item.code)}</small></span>
                            </div>
                        </td>
                        <td data-label="Unit price">${money(item.price)}</td>
                        <td data-label="Quantity"><input class="shop-quantity" data-id="${item.id}" type="number" min="1" max="${item.stock_quantity}" value="${item.qty}" ${editable ? '' : 'disabled'} aria-label="Quantity for ${escapeHtml(item.name)}"></td>
                        <td data-label="Item total">${money(item.line_total)}</td>
                        <td data-label="Remove">${editable ? `<button class="button button-secondary shop-remove" data-id="${item.id}" type="button">Remove item</button>` : ''}</td>
                    </tr>
                `).join('') : '<tr><td colspan="5">Your cart is empty. Scan or enter a product code to begin.</td></tr>';

                summary.innerHTML = `<p><strong>Total Pieces:</strong> ${cart.total_pieces}</p><p><strong>Running Grand Total:</strong> ${money(cart.grand_total)}</p>`;

                document.querySelectorAll('.shop-quantity').forEach(input => {
                    input.addEventListener('change', () => updateItem(input.dataset.id, Number(input.value)));
                });
                document.querySelectorAll('.shop-remove').forEach(button => {
                    button.addEventListener('click', () => updateItem(button.dataset.id, 0));
                });

                finish.disabled = !editable || cart.items.length === 0;
                if (cart.status === 'ready_for_checkout' || cart.status === 'verified_ready') showQr(cart.token);
                renderRating();
                schedulePoll();
            }

            async function updateItem(id, qty) {
                try {
                    const result = await send(itemUrl.replace('ITEM_ID', id), 'PUT', { qty });
                    cart = result.cart;
                    render();
                } catch (error) {
                    showMessage(error.message, true);
                }
            }

            document.addEventListener('pos:code-scanned', async event => {
                if (cart.status !== 'shopping') return;
                try {
                    const result = await send(scanUrl, 'POST', { code: event.detail.code });
                    cart = result.cart;
                    render();
                    showMessage(result.message);
                } catch (error) {
                    showMessage(error.message, true);
                }
            });

            function showQr(token, cartUrl = cart.verification_url || cart.cart_url || token) {
                document.getElementById('cashier-qr-panel').hidden = false;
                document.getElementById('cashier-cart-token').textContent = token;
                const qr = document.getElementById('cashier-cart-qr');
                qr.replaceChildren();
                if (window.QRCode) new QRCode(qr, { text: cartUrl, width: 220, height: 220 });
                else showMessage('QR library could not load. Give the cashier this cart code: ' + token, true);
            }

            finish.addEventListener('click', async () => {
                try {
                    const result = await send(finalizeUrl, 'POST');
                    cart = result.cart;
                    render();
                    showQr(result.qr_value, result.verification_url || result.qr_url);
                    showMessage(result.message);
                } catch (error) {
                    showMessage(error.message, true);
                }
            });

            document.getElementById('submit-rating').addEventListener('click', async () => {
                const selected = document.querySelector('input[name="service_rating"]:checked');
                if (!selected) {
                    showMessage('Please select a rating from 1 to 5 stars.', true);
                    return;
                }
                try {
                    const result = await send(rateUrl, 'POST', {
                        rating: Number(selected.value),
                        feedback: document.getElementById('service-feedback').value,
                    });
                    cart = result.cart;
                    render();
                    showMessage(result.message);
                } catch (error) {
                    showMessage(error.message, true);
                }
            });

            document.querySelector('.scanner-input')?.focus();
            render();
        });
    </script>
    <style>
        .shop-cart { max-width:980px; margin:auto; }
        .shop-cart-product { align-items:center; display:flex; gap:12px; min-width:190px; }
        .shop-product-image,.shop-product-image-placeholder { background:#f1f5f9; border:1px solid #dbe5e3; border-radius:6px; flex:none; height:64px; object-fit:cover; width:64px; }
        .shop-product-image-placeholder { align-items:center; color:#64748b; display:flex; font-size:.65rem; justify-content:center; text-align:center; }
        .cart-token { color:#64748b; font-size:.85rem; overflow-wrap:anywhere; }
        .cart-summary { display:flex; flex-wrap:wrap; gap:28px; font-size:1.15rem; }
        .shop-quantity { width:84px; border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:8px; }
        .qr-panel { margin-top:24px; padding:20px; border:2px solid #0f766e; border-radius:8px; text-align:center; }
        #cashier-cart-qr { display:flex; justify-content:center; margin:16px; }
        .rating-stars { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin:12px 0; }
        .rating-star { background:#f0fdfa; border:1px solid #99f6e4; border-radius:6px; cursor:pointer; padding:8px 12px; }
        .rating-star input { margin-right:4px; }
        #service-feedback { border:1px solid #94a3b8; border-radius:6px; font:inherit; margin:8px 0 12px; padding:10px; width:100%; }
        #rating-thanks { color:#0f766e; font-weight:700; }
        @media(max-width:640px) {
            main { padding:16px; }
            .shop-cart { max-width:480px; }
            .shop-cart .page-actions { align-items:flex-start; flex-direction:column; gap:4px; }
            .shop-cart table { min-width:0; width:100%; }
            .shop-cart thead { display:none; }
            .shop-cart tbody tr { background:#fff; border:1px solid #dbe5e3; border-radius:10px; display:grid; gap:0 12px; grid-template-columns:repeat(2,minmax(0,1fr)); margin:12px 0; overflow:hidden; padding:8px; }
            .shop-cart tbody td { border:0; display:block; min-width:0; overflow-wrap:anywhere; padding:8px; text-align:left; }
            .shop-cart tbody td::before { color:#64748b; content:attr(data-label); display:block; font-size:.72rem; font-weight:700; margin-bottom:3px; text-transform:uppercase; }
            .shop-cart tbody td:first-child,.shop-cart tbody td:last-child { grid-column:1 / -1; }
            .shop-cart-product { min-width:0; }
            .shop-cart tbody td:last-child .button { width:100%; }
            .shop-quantity { max-width:100%; width:100%; }
            .cart-summary { background:#f0fdfa; border-radius:10px; display:grid; font-size:1rem; gap:0 16px; grid-template-columns:1fr 1fr; margin:14px 0; padding:8px 14px; }
            .cart-summary p { margin:8px 0; }
            .shop-cart .button { min-height:46px; }
            .qr-panel { padding:14px; }
        }
    </style>
@endsection
