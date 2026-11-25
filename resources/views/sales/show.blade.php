@extends('layouts.app')

@section('title', 'Sale #'.$sale->id.' Receipt | QuickScanPos')

@section('content')
    <div class="page-actions receipt-actions">
        <h2>Sale Receipt #{{ $sale->id }}</h2>
        <div class="row-actions">
            <a class="button button-secondary" href="{{ route('sales.index') }}">Back to Sales</a>
            <button class="button" type="button" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
    <dl class="receipt-details">
        <div><dt>Date</dt><dd>{{ $sale->created_at->format('M j, Y g:i A') }}</dd></div>
        <div><dt>Cashier</dt><dd>{{ $sale->user?->name ?? 'Deleted user' }}</dd></div>
        <div><dt>Sale number</dt><dd>#{{ $sale->id }}</dd></div>
    </dl>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Barcode</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->barcode }}</td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>₱{{ number_format($item->unit_price, 2) }}</td>
                        <td>₱{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><th colspan="4">Subtotal</th><td>₱{{ number_format($sale->subtotal, 2) }}</td></tr>
                <tr><th colspan="4">Tax</th><td>₱{{ number_format($sale->tax, 2) }}</td></tr>
                <tr><th colspan="4">Discount</th><td>₱{{ number_format($sale->discount, 2) }}</td></tr>
                <tr><th colspan="4">Total</th><td><strong>₱{{ number_format($sale->total, 2) }}</strong></td></tr>
            </tfoot>
        </table>
    </div>
    <p class="receipt-thanks">Thank you for shopping with us.</p>

    <style>
        .receipt-details { display:grid; gap:16px; grid-template-columns:repeat(3,minmax(0,1fr)); margin:24px 0; }
        .receipt-details dt { color:var(--muted); margin:0; }
        .receipt-details dd { font-weight:700; }
        .receipt-thanks { color:var(--muted); margin-top:28px; text-align:center; }
        @media print {
            body { background:#fff; }
            .site-header,.site-shell>footer,.receipt-actions a,.receipt-actions button { display:none !important; }
            .site-shell { max-width:none; padding:0; }
            main { border:0; padding:0; }
            table { min-width:0; }
        }
        @media(max-width:640px) { .receipt-details { grid-template-columns:1fr; } }
    </style>
@endsection
