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
            <p>Keep this page open until checkout is complete.</p>
        </section>
    </section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const initialCart = @json($cartData);
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const scanUrl = @json(route('shop.cart.scan', $cart->token));
            const finalizeUrl = @json(route('shop.cart.finalize', $cart->token));
            const itemUrl = @json(route('shop.cart.item.update', ['token' => $cart->token, 'item' => 'ITEM_ID']));
            const body = document.getElementById('shop-cart-items');
            const summary = document.getElementById('shop-summary');
            const message = document.getElementById('shop-message');
            const finish = document.getElementById('finish-shopping');
            let cart = initialCart;
            const editable = cart.status === 'shopping';

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
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
                return result;
            };

            function render() {
                body.innerHTML = cart.items.length ? cart.items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.name)}<br><small>${escapeHtml(item.code)}</small></td>
                        <td>${money(item.price)}</td>
                        <td><input class="shop-quantity" data-id="${item.id}" type="number" min="0" max="${item.stock_quantity}" value="${item.qty}" ${editable ? '' : 'disabled'} aria-label="Quantity for ${escapeHtml(item.name)}"></td>
                        <td>${money(item.line_total)}</td>
                        <td>${editable ? `<button class="button button-secondary shop-remove" data-id="${item.id}" type="button">Remove</button>` : ''}</td>
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
                if (!editable) return;
                try {
                    const result = await send(scanUrl, 'POST', { code: event.detail.code });
                    cart = result.cart;
                    render();
                    showMessage(result.message);
                } catch (error) {
                    showMessage(error.message, true);
                }
            });

            function showQr(token) {
                document.getElementById('cashier-qr-panel').hidden = false;
                document.getElementById('cashier-cart-token').textContent = token;
                const qr = document.getElementById('cashier-cart-qr');
                qr.replaceChildren();
                if (window.QRCode) new QRCode(qr, { text: token, width: 220, height: 220 });
                else showMessage('QR library could not load. Give the cashier this cart code: ' + token, true);
            }

            finish.addEventListener('click', async () => {
                try {
                    const result = await send(finalizeUrl, 'POST');
                    cart = result.cart;
                    render();
                    showQr(result.qr_value);
                    showMessage(result.message);
                } catch (error) {
                    showMessage(error.message, true);
                }
            });

            document.querySelector('.scanner-input').focus();
            render();
        });
    </script>
    <style>
        .shop-cart { max-width:980px; margin:auto; }
        .cart-token { color:#64748b; font-size:.85rem; overflow-wrap:anywhere; }
        .cart-summary { display:flex; flex-wrap:wrap; gap:28px; font-size:1.15rem; }
        .shop-quantity { width:84px; border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:8px; }
        .qr-panel { margin-top:24px; padding:20px; border:2px solid #0f766e; border-radius:8px; text-align:center; }
        #cashier-cart-qr { display:flex; justify-content:center; margin:16px; }
        @media(max-width:640px) { main { padding:16px; } .page-actions { align-items:flex-start; flex-direction:column; } .shop-cart .button { min-height:44px; } }
    </style>
@endsection
