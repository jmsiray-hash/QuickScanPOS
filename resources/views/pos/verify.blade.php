@extends('layouts.app')

@section('title', 'Verify Cart Items | POS Workspace')

@section('content')
    <section class="verify-page">
        <h2>Cashier Item Verification</h2>
        <p>Cart for <strong>{{ $cart->customer_name }}</strong> · {{ $cart->token }}</p>
        <div id="verify-message" class="alert" role="status" hidden></div>
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
            let cart = @json($cartData);
            const body = document.getElementById('verify-items');
            const summary = document.getElementById('verify-summary');
            const message = document.getElementById('verify-message');
            const banner = document.getElementById('verified-banner');
            const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);

            function render() {
                body.innerHTML = cart.items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.name)}<br><small>${escapeHtml(item.code)}</small></td>
                        <td>${item.qty}</td>
                        <td>${item.checked_qty}</td>
                        <td><button class="button button-secondary verify-one" data-code="${escapeHtml(item.code)}" type="button" ${item.is_checked ? 'disabled' : ''}>Check one</button></td>
                    </tr>
                `).join('');
                summary.innerHTML = `<p><strong>Total Expected Pieces:</strong> ${cart.total_expected_pieces} &nbsp; <strong>Total Checked Pieces:</strong> ${cart.total_checked_pieces}</p>`;
                banner.hidden = !cart.is_verified;
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

            document.addEventListener('pos:code-scanned', event => checkItem(event.detail.code));
            document.querySelector('.scanner-input').focus();
            render();
        });
    </script>
    <style>
        .verify-page { max-width:900px; margin:auto; }
        .verified-banner { background:#dcfce7; border:2px solid #16a34a; border-radius:8px; color:#166534; font-size:clamp(1.1rem,3vw,1.7rem); font-weight:800; margin:18px 0; padding:20px; text-align:center; }
        @media(max-width:640px) { main { padding:16px; } .verify-one { min-height:44px; } }
    </style>
@endsection
