@extends('layouts.app')

@section('title', 'Customer Details | POS Workspace')

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
        <dt>Created</dt><dd>{{ $customer->created_at }}</dd>
    </dl>
@endsection
