@extends('layouts.app')

@section('title', 'Customer Ratings | QuickScanPos')

@section('content')
    <header class="ratings-heading">
        <div>
            <p class="ratings-eyebrow">CUSTOMER FEEDBACK</p>
            <h1>Ratings</h1>
            <p>Ratings customers submit from the receipt on their phone after checkout.</p>
        </div>
        <a class="button button-secondary" href="{{ route('customer-activity.index') }}">Customer carts</a>
    </header>

    <section class="ratings-summary" aria-label="Rating summary">
        <article><span>Total ratings</span><strong>{{ number_format((int) $summary->rating_count) }}</strong></article>
        <article><span>Average rating</span><strong>{{ $summary->average_rating === null ? '—' : number_format((float) $summary->average_rating, 1).'/5' }}</strong></article>
    </section>

    <div class="table-wrap">
        <table class="ratings-table">
            <thead>
                <tr><th>Rating</th><th>Customer</th><th>Feedback</th><th>Receipt</th><th>Submitted</th></tr>
            </thead>
            <tbody>
                @forelse ($ratings as $cart)
                    <tr>
                        <td><span class="rating-value" aria-label="{{ $cart->service_rating }} out of 5 stars">{{ str_repeat('★', $cart->service_rating) }}<small>/5</small></span></td>
                        <td><strong>{{ $cart->customer_name }}</strong><br><small>{{ $cart->items_count }} {{ $cart->items_count === 1 ? 'item' : 'items' }}</small></td>
                        <td>{{ $cart->service_feedback ?: 'No written feedback' }}</td>
                        <td>{{ $cart->sale_id ? '#'.$cart->sale_id : '—' }}</td>
                        <td>{{ $cart->rated_at->format('M j, Y g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ratings-empty">No customer ratings yet. Ratings submitted from paid Scan &amp; Go receipts will appear here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $ratings->links() }}

    <style>
        .ratings-heading { align-items:center; display:flex; gap:16px; justify-content:space-between; margin-bottom:22px; }
        .ratings-eyebrow { color:var(--brand); font-size:.68rem; font-weight:800; letter-spacing:.14em; margin:0 0 4px; }
        .ratings-heading h1 { font-size:clamp(1.7rem,3vw,2.25rem); margin:0 0 5px; }
        .ratings-heading p:last-child { color:var(--muted); margin:0; }
        .ratings-summary { display:grid; gap:12px; grid-template-columns:repeat(2,minmax(0,200px)); margin-bottom:18px; }
        .ratings-summary article { background:var(--wash); border:1px solid #cce9df; border-radius:9px; display:grid; gap:3px; padding:13px 15px; }
        .ratings-summary span { color:var(--muted); font-size:.78rem; }
        .ratings-summary strong { color:var(--brand-dark); font-size:1.35rem; }
        .ratings-table { margin-top:0; }
        .ratings-table td { vertical-align:top; }
        .rating-value { color:#b7791f; font-size:1rem; letter-spacing:.04em; white-space:nowrap; }
        .rating-value small { color:#7b8791; font-size:.72rem; letter-spacing:0; margin-left:3px; }
        .ratings-table td>small { color:var(--muted); }
        .ratings-empty { color:var(--muted); padding:24px; text-align:center; }
        @media(max-width:640px) {
            .ratings-heading { align-items:flex-start; flex-direction:column; }
            .ratings-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .ratings-table { min-width:720px; }
        }
    </style>
@endsection
