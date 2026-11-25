@extends('layouts.app')

@section('title', 'Customers | QuickScanPos')

@section('content')
    <div class="page-actions">
        <h2>Customer List</h2>
        <a class="button" href="{{ route('customers.create') }}">Create Customer</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Store discount quota (used / remaining)</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->id }}</td>
                        <td>{{ $customer->full_name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->phone }}</td>
                        <td>₱{{ number_format($customer->monthly_discount_used, 2) }} / ₱{{ number_format($customer->monthly_discount_limit, 2) }}<br><small>₱{{ number_format($customer->monthly_discount_remaining, 2) }} remaining this month</small></td>
                        <td>{{ $customer->created_at }}</td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('customers.show', $customer) }}">View</a>
                                <a href="{{ route('customers.edit', $customer) }}">Edit</a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No customers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
