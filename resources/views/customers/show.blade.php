@extends('layouts.app')

@section('title', 'Customer Details | QuickScanPos')

@section('content')
    <div class="page-actions">
        <h2>Customer Details</h2>
        <div class="row-actions">
            <a class="button" href="{{ route('customers.edit', $customer) }}">Edit Customer</a>
            <a class="button button-secondary" href="{{ route('customers.index') }}">Back</a>
        </div>
    </div>
    <dl>
        <dt>Full Name</dt><dd>{{ $customer->full_name }}</dd>
        <dt>Email</dt><dd>{{ $customer->email }}</dd>
        <dt>Phone</dt><dd>{{ $customer->phone ?: 'Not provided' }}</dd>
        <dt>Discount ID</dt><dd>{{ $customer->discount_id_type ? strtoupper($customer->discount_id_type).' · '.$customer->discount_id_number : 'Not recorded' }}</dd>
        <dt>Monthly store-discount quota</dt><dd>₱{{ number_format($customer->monthly_discount_used, 2) }} used / ₱{{ number_format($customer->monthly_discount_limit, 2) }} total · ₱{{ number_format($customer->monthly_discount_remaining, 2) }} remaining</dd>
        <dt>Cashier discount reminder</dt><dd>{{ $customer->store_discount_reminder ? 'Customer usually asks for a store discount' : 'No reminder' }}</dd>
        <dt>Created</dt><dd>{{ $customer->created_at }}</dd>
    </dl>
    <h3>Purchase History</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Sale</th><th>Date</th><th>Items</th><th>Discount</th><th>Total</th><th></th></tr></thead>
            <tbody>
                @forelse ($customer->sales as $sale)
                    <tr>
                        <td>#{{ $sale->id }}</td>
                        <td>{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $sale->items->sum('quantity') }}</td>
                        <td>₱{{ number_format($sale->discount, 2) }}</td>
                        <td>₱{{ number_format($sale->total, 2) }}</td>
                        <td><a href="{{ route('sales.show', $sale) }}">View receipt</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No purchases recorded for this customer.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
