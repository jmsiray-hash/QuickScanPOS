@extends('layouts.app')

@section('title', 'Sales Reports | QuickScanPos')

@section('content')
    <div class="page-actions">
        <div>
            <h2>Sales Reports</h2>
            <p>Completed sales and sold item performance for the selected dates.</p>
        </div>
    </div>
    <form method="GET" action="{{ route('sales.index') }}" class="report-filters">
        <label for="from">From</label>
        <input id="from" name="from" type="date" value="{{ $from }}" required>
        <label for="to">To</label>
        <input id="to" name="to" type="date" value="{{ $to }}" required>
        <button class="button" type="submit">Run Report</button>
        <a href="{{ route('sales.index') }}">Today</a>
    </form>

    <section class="report-summary" aria-label="Sales totals">
        <div><span>Sales revenue</span><strong>₱{{ number_format($revenue, 2) }}</strong></div>
        <div><span>Transactions</span><strong>{{ number_format($transactionCount) }}</strong></div>
        <div><span>Units sold</span><strong>{{ number_format($unitsSold) }}</strong></div>
    </section>

    <h3>Top sold items</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Product</th><th>Barcode</th><th>Quantity sold</th><th>Revenue</th></tr></thead>
            <tbody>
                @forelse($topItems as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->barcode }}</td>
                        <td>{{ number_format($item->quantity_sold) }}</td>
                        <td>₱{{ number_format($item->revenue, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No sold items in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3>Completed transactions</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Sale</th><th>Date and time</th><th>Cashier</th><th>Lines</th><th>Total</th><th></th></tr></thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>#{{ $sale->id }}</td>
                        <td>{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $sale->user?->name ?? 'Deleted user' }}</td>
                        <td>{{ $sale->items_count }}</td>
                        <td>₱{{ number_format($sale->total, 2) }}</td>
                        <td><a href="{{ route('sales.show', $sale) }}">View receipt</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No completed sales in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $sales->links() }}

    <style>
        .report-filters { align-items:end; display:flex; flex-wrap:wrap; gap:10px; margin:20px 0; }
        .report-filters label { font-weight:700; }
        .report-filters input { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:9px; }
        .report-summary { display:grid; gap:12px; grid-template-columns:repeat(3,minmax(0,1fr)); margin:20px 0; }
        .report-summary div { background:#f0fdfa; border:1px solid #ccfbf1; border-radius:8px; display:grid; gap:4px; padding:16px; }
        .report-summary span { color:var(--muted); }
        .report-summary strong { color:var(--brand-dark); font-size:1.5rem; }
        @media(max-width:640px) { .report-summary { grid-template-columns:1fr; } }
    </style>
@endsection
