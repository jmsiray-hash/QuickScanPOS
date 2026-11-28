@extends('layouts.app')

@section('title', 'Scan & Go Customer Activity | QuickScanPos')

@section('content')
    <div class="activity-page-heading">
        <div>
            <p class="activity-eyebrow">{{ $activityView === 'live' ? 'LIVE REGISTER VIEW' : 'COMPLETED VISITS' }}</p>
            <h1>{{ $activityView === 'live' ? 'Live customer activity' : 'Customer history' }}</h1>
            <p>{{ $activityView === 'live'
                ? 'Active customer carts update here automatically. Paid, rated, or expired visits move to History.'
                : 'Review completed, rated, and expired customer visits with their final item counts.' }}</p>
        </div>
        <div class="activity-heading-actions">
            <nav class="activity-view-toggle" aria-label="Customer activity views">
                <a class="{{ $activityView === 'live' ? 'is-active' : '' }}" href="{{ route('customer-activity.index', ['view' => 'live']) }}" @if ($activityView === 'live') aria-current="page" @endif>Live</a>
                <a class="{{ $activityView === 'history' ? 'is-active' : '' }}" href="{{ route('customer-activity.index', ['view' => 'history']) }}" @if ($activityView === 'history') aria-current="page" @endif>History</a>
            </nav>
            <a class="button button-secondary" href="{{ route('customers.index') }}">Customer Records</a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Customer / Cart</th><th>Status</th><th>Items</th><th>Last updated</th><th>Service rating</th><th></th></tr></thead>
            <tbody id="customer-cart-list">
                @forelse ($carts as $cart)
                    <tr>
                        <td><strong>{{ $cart->customer_name }}</strong><br><small>{{ $cart->token }}</small></td>
                        <td><span class="activity-status-badge {{ in_array($cart->status, ['paid', 'expired'], true) ? 'is-complete' : '' }}">{{ ucwords(str_replace('_', ' ', $cart->status)) }}</span></td>
                        <td>{{ (int) $cart->items_sum_qty }}</td>
                        <td>{{ $cart->updated_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $cart->service_rating ? $cart->service_rating.'/5' : 'Not rated' }}</td>
                        <td><a href="{{ route('customer-activity.show', $cart) }}">View customer cart</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ $activityView === 'live'
                        ? 'No active customer carts right now. New visits will appear here automatically.'
                        : 'No paid, rated, or expired customer visits have been recorded yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $carts->links() }}
    <style>
        .activity-page-heading { align-items:flex-end; display:flex; gap:20px; justify-content:space-between; margin-bottom:22px; }
        .activity-page-heading h1 { font-size:clamp(1.7rem,3vw,2.25rem); margin:0 0 6px; }
        .activity-page-heading p:last-child { color:var(--muted); margin:0; }
        .activity-eyebrow { color:var(--brand); font-size:.68rem; font-weight:800; letter-spacing:.14em; margin:0 0 4px; }
        .activity-heading-actions { align-items:center; display:flex; flex-wrap:wrap; gap:10px; }
        .activity-view-toggle { background:#edf4f2; border:1px solid #d5e3df; border-radius:8px; display:flex; gap:3px; padding:3px; }
        .activity-view-toggle a { border-radius:6px; color:#526762; font-size:.82rem; font-weight:700; padding:8px 14px; text-decoration:none; }
        .activity-view-toggle a.is-active { background:#fff; box-shadow:0 1px 3px #183e361f; color:var(--brand-dark); }
        .activity-status-badge { background:#e4f6ef; border-radius:999px; color:#17654d; display:inline-block; font-size:.75rem; font-weight:700; padding:4px 9px; white-space:nowrap; }
        .activity-status-badge.is-complete { background:#e9eef2; color:#52636d; }
        @media(max-width:720px) { .activity-page-heading { align-items:flex-start; flex-direction:column; } }
    </style>
    <script>
        let activityRefreshInProgress = false;
        async function refreshCustomerActivity() {
            if (document.visibilityState !== 'visible' || activityRefreshInProgress) return;
            activityRefreshInProgress = true;
            try {
                const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
                if (!response.ok) return;
                const html = await response.text();
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextRows = nextDocument.getElementById('customer-cart-list');
                if (nextRows) document.getElementById('customer-cart-list').innerHTML = nextRows.innerHTML;
            } catch (error) {
                console.error('Could not refresh Scan & Go customer activity.', error);
            } finally {
                activityRefreshInProgress = false;
            }
        }
        window.Echo?.private('pos-sync').listen('.pos.sync', event => {
            if (['cart', 'product', 'sale', 'scan'].includes(event.resource)) refreshCustomerActivity();
        });
        window.setInterval(refreshCustomerActivity, window.Echo ? 15000 : 1000);
    </script>
@endsection
