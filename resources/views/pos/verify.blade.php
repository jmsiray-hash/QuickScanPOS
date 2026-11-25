@extends('layouts.app')

@section('title', 'Verify Cart Items | QuickScanPos')

@section('content')
    <section class="verify-page">
        <p class="verify-eyebrow">QUICKSCANPOS · CASHIER PHONE</p>
        <h1>Check the items</h1>
        <p>Scan every physical product in the cashier's cart. You can scan with this phone's camera or enter a product code. Checkout unlocks after all pieces match.</p>
        <p>Cart for <strong>{{ $cart->customer_name }}</strong> · {{ $cart->token }}</p>
        <div id="verify-message" class="alert" role="status" hidden></div>
        <div id="waiting-for-items" class="waiting-for-items" @if ($cartData['items']->isNotEmpty()) hidden @endif>
            Phone connected. Waiting for the cashier to add items. This page will update automatically.
        </div>
        <div id="verified-banner" class="verified-banner" @if (! $cart->is_verified) hidden @endif>{{ $readyLabel }}</div>
        <div id="verify-summary"></div>
        @include('components.code-scanner')
        <div class="table-wrap">
            <table>
                <thead><tr><th>Expected Product / Code</th><th>Expected Qty</th><th>Checked Qty</th><th>Action</th></tr></thead>
                <tbody id="verify-items"></tbody>
            </table>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const checkUrl = @json(route('pos.verify.check', $cart->verification_code));
            const statusUrl = @json(route('pos.verify-status', $cart->verification_code));
            let cart = @json($cartData);
            let previousChecks = JSON.stringify(cart.items.map(item => [item.product_id, item.qty, item.checked_qty]));
            let statusTimer = null;
            const body = document.getElementById('verify-items');
            const summary = document.getElementById('verify-summary');
            const message = document.getElementById('verify-message');
            const banner = document.getElementById('verified-banner');
            const waitingNotice = document.getElementById('waiting-for-items');
            const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);

            function render() {
                body.innerHTML = cart.items.length ? cart.items.map(item => `
                    <tr>
                        <td data-label="Expected product">${escapeHtml(item.name)}<br><small>${escapeHtml(item.code)}</small></td>
                        <td data-label="Expected qty">${item.qty}</td>
                        <td data-label="Checked qty">${item.checked_qty}</td>
                        <td data-label="Action"><button class="button button-secondary verify-one" data-code="${escapeHtml(item.code)}" type="button" ${item.is_checked ? 'disabled' : ''}>Check one</button></td>
                    </tr>
                `).join('') : '<tr><td colspan="4">Waiting for the cashier to add items to this checkout.</td></tr>';
                summary.innerHTML = `<p><strong>Total Expected Pieces:</strong> ${cart.total_expected_pieces} &nbsp; <strong>Total Checked Pieces:</strong> ${cart.total_checked_pieces}</p>`;
                banner.hidden = !cart.is_verified;
                waitingNotice.hidden = cart.items.length > 0;
                body.querySelectorAll('.verify-one').forEach(button => {
                    button.addEventListener('click', () => checkItem(button.dataset.code));
                });
            }

            async function checkItem(code) {
                try {
                    const response = await fetch(checkUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ code }),
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'The item could not be checked.');
                    cart = result.cart;
                    message.textContent = result.message;
                    message.className = 'alert alert-success';
                    message.hidden = false;
                    render();
                } catch (error) {
                    message.textContent = error.message;
                    message.className = 'alert alert-errors';
                    message.hidden = false;
                }
            }

            async function refreshCart() {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Could not refresh the cashier cart.');
                const nextChecks = JSON.stringify(result.cart.items.map(item => [item.product_id, item.qty, item.checked_qty]));
                if (nextChecks !== previousChecks) {
                    const checksWereReset = result.cart.total_checked_pieces < cart.total_checked_pieces;
                    cart = result.cart;
                    previousChecks = nextChecks;
                    render();
                    if (checksWereReset) {
                        message.textContent = 'The cashier updated the cart. Scan and verify all expected items again.';
                        message.className = 'alert alert-errors';
                        message.hidden = false;
                    }
                } else {
                    cart = result.cart;
                    banner.hidden = !cart.is_verified;
                }
            }

            document.addEventListener('pos:code-scanned', event => checkItem(event.detail.code));
            document.querySelector('.scanner-input').focus();
            render();
            statusTimer = window.setInterval(async () => {
                try {
                    await refreshCart();
                } catch (error) {
                    message.textContent = error.message || 'Could not refresh the cashier cart.';
                    message.className = 'alert alert-errors';
                    message.hidden = false;
                }
            }, 2000);
            window.addEventListener('pagehide', () => {
                if (statusTimer) window.clearInterval(statusTimer);
            });
        });
    </script>
    <style>
        .verify-page { margin:auto; max-width:720px; }
        .verify-eyebrow { color:#0f766e; font-size:.76rem; font-weight:800; letter-spacing:.12em; }
        .verify-page h1 { font-size:clamp(1.8rem,6vw,2.4rem); }
        .verified-banner { background:#dcfce7; border:2px solid #16a34a; border-radius:8px; color:#166534; font-size:clamp(1.1rem,3vw,1.7rem); font-weight:800; margin:18px 0; padding:20px; text-align:center; }
        .waiting-for-items { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1e40af; margin:16px 0; padding:14px; }
        @media(max-width:640px) {
            main { padding:16px; }
            .verify-page { max-width:480px; }
            .verify-page table { min-width:0; width:100%; }
            .verify-page thead { display:none; }
            .verify-page tbody tr { border:1px solid #dbe5e3; border-radius:10px; display:grid; gap:0 12px; grid-template-columns:1fr 1fr; margin:12px 0; padding:8px; }
            .verify-page tbody td { border:0; display:block; min-width:0; overflow-wrap:anywhere; padding:8px; }
            .verify-page tbody td:first-child,.verify-page tbody td:last-child { grid-column:1 / -1; }
            .verify-page tbody td::before { color:#64748b; content:attr(data-label); display:block; font-size:.72rem; font-weight:700; margin-bottom:3px; text-transform:uppercase; }
            .verify-one { min-height:46px; width:100%; }
        }
    </style>
@endsection
