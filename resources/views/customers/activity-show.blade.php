@extends('layouts.app')

@section('title', 'Customer Cart | QuickScanPos')

@section('content')
    <div class="page-actions">
        <div>
            <h2>{{ $cart->customer_name }}</h2>
            <p>Scan &amp; Go cart <strong>{{ $cart->token }}</strong></p>
        </div>
        <a class="button button-secondary" href="{{ route('customer-activity.index') }}">Back to Customer Activity</a>
    </div>
    <p id="cart-status" class="activity-status">{{ ucwords(str_replace('_', ' ', $cart->status)) }}</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Product</th><th>Code</th><th>Quantity</th><th>Price</th><th>Line total</th></tr></thead>
            <tbody id="customer-items">
                @forelse ($cartData['items'] as $item)
                    <tr><td>{{ $item['name'] }}</td><td>{{ $item['code'] ?: '—' }}</td><td>{{ $item['qty'] }}</td><td>₱{{ number_format($item['price'], 2) }}</td><td>₱{{ number_format($item['line_total'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="5">No items added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p id="cart-total"><strong>Total:</strong> ₱{{ number_format($cartData['grand_total'], 2) }}</p>
    <section id="customer-receipt" class="customer-receipt" @if(!$cartData['receipt']) hidden @endif>
        <h3>Payment receipt</h3>
        <div id="receipt-content"></div>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const statusUrl = @json(route('customer-activity.status', $cart));
            const itemsBody = document.getElementById('customer-items');
            const statusLabel = document.getElementById('cart-status');
            const totalLabel = document.getElementById('cart-total');
            const receiptPanel = document.getElementById('customer-receipt');
            const receiptContent = document.getElementById('receipt-content');
            const money = value => '₱' + Number(value).toFixed(2);
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);
            function render(data) {
                statusLabel.textContent = String(data.status).replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
                itemsBody.innerHTML = data.items.length
                    ? data.items.map(item => `<tr><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.code || '—')}</td><td>${item.qty}</td><td>${money(item.price)}</td><td>${money(item.line_total)}</td></tr>`).join('')
                    : '<tr><td colspan="5">No items added yet.</td></tr>';
                totalLabel.innerHTML = `<strong>Total:</strong> ${money(data.receipt?.total ?? data.grand_total)}`;
                if (!data.receipt) return;
                receiptPanel.hidden = false;
                const receipt = data.receipt;
                const rows = receipt.items.map(item => `<tr><td>${escapeHtml(item.name)}${item.is_promo ? ' <strong>PROMO</strong>' : ''}</td><td>${item.quantity}</td><td>${money(item.unit_price)}</td><td>${money(item.line_total)}</td></tr>`).join('');
                receiptContent.innerHTML = `
                    <p class="paid-badge">${receipt.paid ? 'PAID' : 'VOIDED'}</p>
                    <p><strong>${escapeHtml(receipt.store_name)}</strong><br>${escapeHtml(receipt.store_address)}<br>Receipt #${receipt.id} · ${escapeHtml(receipt.created_at)}</p>
                    <div class="table-wrap"><table><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>${rows}</tbody></table></div>
                    <p>Subtotal ${money(receipt.subtotal)} · Discount ${money(receipt.discount)} · Tax ${money(receipt.tax)}<br><strong>Paid total: ${money(receipt.total)}</strong><br>Payment: ${escapeHtml(String(receipt.payment_method || '').toUpperCase())}${receipt.payment_reference ? ' · Reference: ' + escapeHtml(receipt.payment_reference) : ''}</p>
                    ${data.service_rating ? `<p>Service rating: ${data.service_rating}/5${data.service_feedback ? ' · ' + escapeHtml(data.service_feedback) : ''}</p>` : ''}
                    <button class="button button-secondary" type="button" onclick="window.print()">Print Receipt</button>`;
            }
            render(@json($cartData));
            if (!['paid', 'expired'].includes(@json($cart->status))) {
                window.setInterval(async () => {
                    try {
                        const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                        if (!response.ok) return;
                        const result = await response.json();
                        render(result.cart);
                        if (['paid', 'expired'].includes(result.cart.status)) window.location.reload();
                    } catch (error) {
                        console.error('Could not refresh customer cart status.', error);
                    }
                }, 4000);
            }
        });
    </script>
    <style>
        .activity-status { background:#f0fdfa; border-radius:8px; color:#115e59; font-weight:700; padding:12px; }
        .customer-receipt { border:1px solid #99f6e4; border-radius:8px; margin-top:24px; padding:18px; }
        .paid-badge { background:#dcfce7; border-radius:999px; color:#166534; display:inline-block; font-weight:800; padding:6px 16px; }
        @media print { .site-header,.site-shell>footer,.page-actions { display:none !important; } .site-shell { max-width:none; padding:0; } main { border:0; padding:0; } }
    </style>
@endsection
