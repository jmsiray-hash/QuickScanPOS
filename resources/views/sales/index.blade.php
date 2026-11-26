@extends('layouts.app')

@section('title', 'Sales Reports | QuickScanPos')

@section('content')
    <div class="page-actions">
        <div>
            <h2>Sales Reports</h2>
            <p>Transaction history and sold item performance for the selected dates.</p>
        </div>
    </div>
    @if ($activeShift)
        <section class="shift-panel">
                <h3>Close Shift / Z-Reading</h3>
                <p>Opened {{ $activeShift->opened_at->format('M j, Y g:i A') }} with ₱{{ number_format($activeShift->opening_cash, 2) }}. Enter the drawer count to close.</p>
                <div class="shift-breakdown">
                    <span>Cash sales: <strong>₱{{ number_format($activeShiftTotals['cash_sales'], 2) }}</strong></span>
                    <span>Cash refunds/voids: <strong>₱{{ number_format($activeShiftTotals['cash_refunds'], 2) }}</strong></span>
                    <span>GCash: <strong>₱{{ number_format($activeShiftTotals['gcash_sales'], 2) }}</strong></span>
                    <span>Maya: <strong>₱{{ number_format($activeShiftTotals['maya_sales'], 2) }}</strong></span>
                    <span>Card: <strong>₱{{ number_format($activeShiftTotals['card_sales'], 2) }}</strong></span>
                    <span>Expected cash: <strong>₱{{ number_format((float) $activeShift->opening_cash + $activeShiftTotals['cash_sales'] - $activeShiftTotals['cash_refunds'], 2) }}</strong></span>
                </div>
                <form method="POST" action="{{ route('sales.shift.close') }}" class="report-filters">
                    @csrf
                    <label for="actual-cash">Actual cash counted</label>
                    <input id="actual-cash" name="actual_cash" type="number" step="0.01" min="0" required>
                    <button class="button" type="submit">Close Shift &amp; Save Z-Reading</button>
                    @error('actual_cash')<p class="error">{{ $message }}</p>@enderror
                </form>
        </section>
    @endif
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

    <h3>Transaction history</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Sale</th><th>Date and time</th><th>Cashier</th><th>Customer</th><th>Payment</th><th>Lines</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>#{{ $sale->id }}</td>
                        <td>{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $sale->user?->name ?? 'Deleted user' }}</td>
                        <td>{{ $sale->customer_name ?: 'Walk-in' }}</td>
                        <td>{{ strtoupper($sale->payment_method) }}</td>
                        <td>{{ $sale->items_count }}</td>
                        <td>₱{{ number_format($sale->total, 2) }}</td>
                        <td>{{ ucfirst($sale->status) }}</td>
                        <td class="transaction-actions">
                            <a href="{{ route('sales.show', $sale) }}">View receipt</a>
                            @if ($sale->status === 'completed' && auth()->user()->canVoidSales())
                                <form method="POST" action="{{ route('sales.void', $sale) }}" onsubmit="const reason=prompt('Enter the reason for voiding sale #{{ $sale->id }}:'); if (!reason || reason.trim().length < 5) return false; this.querySelector('[name=void_reason]').value=reason.trim(); return confirm('Void this sale and restore its stock?');">
                                    @csrf
                                    <input type="hidden" name="void_reason">
                                    <button class="void-button" type="submit">Void transaction</button>
                                </form>
                            @elseif ($sale->status === 'voided')
                                <small>By {{ $sale->voidedBy?->name ?? 'Deleted user' }}: {{ $sale->void_reason }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">No sales in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $sales->links() }}

    <h3>Recent Shift Z-Readings</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cashier</th><th>Opened</th><th>Closed</th><th>Opening fund</th><th>Cash sales</th><th>Cash refunds</th><th>GCash</th><th>Maya</th><th>Card</th><th>Expected / counted</th><th>Over / short</th></tr></thead>
            <tbody>
                @forelse($shifts as $shift)
                    <tr>
                        <td>{{ $shift->user?->name ?? 'Deleted user' }}</td>
                        <td>{{ $shift->opened_at->format('M j, g:i A') }}</td>
                        <td>{{ $shift->closed_at?->format('M j, g:i A') ?? 'Open' }}</td>
                        <td>₱{{ number_format($shift->opening_cash, 2) }}</td>
                        <td>₱{{ number_format($shift->cash_sales, 2) }}</td>
                        <td>₱{{ number_format($shift->cash_refunds, 2) }}</td>
                        <td>₱{{ number_format($shift->gcash_sales, 2) }}</td>
                        <td>₱{{ number_format($shift->maya_sales, 2) }}</td>
                        <td>₱{{ number_format($shift->card_sales, 2) }}</td>
                        <td>@if($shift->expected_cash !== null) ₱{{ number_format($shift->expected_cash, 2) }} / ₱{{ number_format($shift->actual_cash, 2) }} @else — @endif</td>
                        <td>@if($shift->cash_over_short !== null) ₱{{ number_format($shift->cash_over_short, 2) }} @else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="11">No shifts recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <style>
        .report-filters { align-items:end; display:flex; flex-wrap:wrap; gap:10px; margin:20px 0; }
        .report-filters label { font-weight:700; }
        .report-filters input { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:9px; }
        .report-summary { display:grid; gap:12px; grid-template-columns:repeat(3,minmax(0,1fr)); margin:20px 0; }
        .report-summary div { background:#f0fdfa; border:1px solid #ccfbf1; border-radius:8px; display:grid; gap:4px; padding:16px; }
        .report-summary span { color:var(--muted); }
        .report-summary strong { color:var(--brand-dark); font-size:1.5rem; }
        .shift-panel { background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; margin:20px 0; padding:18px; }
        .shift-panel h3 { margin-top:0; }
        .shift-breakdown { display:flex; flex-wrap:wrap; gap:8px 18px; }
        .transaction-actions { min-width:150px; }
        .transaction-actions form { margin-top:6px; }
        .void-button { background:transparent; border:0; color:#b91c1c; cursor:pointer; font:inherit; padding:0; text-decoration:underline; }
        @media(max-width:640px) { .report-summary { grid-template-columns:1fr; } }
    </style>
@endsection
