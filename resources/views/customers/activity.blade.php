@extends('layouts.app')

@section('title', 'Scan & Go Customer Activity | QuickScanPos')

@section('content')
    <div class="page-actions">
        <div>
            <h2>Scan &amp; Go Customer Activity</h2>
            <p>Customers appear here as soon as they open the store entrance QR. Open a row to see their cart update while they shop.</p>
        </div>
        <a class="button button-secondary" href="{{ route('customers.index') }}">Customer Records</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Customer / Cart</th><th>Status</th><th>Items</th><th>Last updated</th><th>Service rating</th><th></th></tr></thead>
            <tbody id="customer-cart-list">
                @forelse ($carts as $cart)
                    <tr>
                        <td><strong>{{ $cart->customer_name }}</strong><br><small>{{ $cart->token }}</small></td>
                        <td>{{ ucwords(str_replace('_', ' ', $cart->status)) }}</td>
                        <td>{{ $cart->items_count }}</td>
                        <td>{{ $cart->updated_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $cart->service_rating ? $cart->service_rating.'/5' : 'Not rated' }}</td>
                        <td><a href="{{ route('customer-activity.show', $cart) }}">View customer cart</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No current customer visits yet. They will appear when the store entrance QR is opened.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $carts->links() }}
    <script>
        window.setInterval(async () => {
            try {
                const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
                if (!response.ok) return;
                const html = await response.text();
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextRows = nextDocument.getElementById('customer-cart-list');
                if (nextRows) document.getElementById('customer-cart-list').innerHTML = nextRows.innerHTML;
            } catch (error) {
                console.error('Could not refresh Scan & Go customer activity.', error);
            }
        }, 5000);
    </script>
@endsection
