@extends('layouts.app')

@section('title', 'Edit Customer | QuickScanPos')

@section('content')
    <h2>Edit Customer</h2>
    <form class="form-grid" action="{{ route('customers.update', $customer) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="field">
            <label for="full_name">Full Name</label>
            <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $customer->full_name) }}" required maxlength="100">
            @error('full_name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" required maxlength="100">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone', $customer->phone) }}" maxlength="20">
            @error('phone')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="row-actions">
            <button class="button" type="submit">Update Customer</button>
            <a class="button button-secondary" href="{{ route('customers.index') }}">Cancel</a>
        </div>
    </form>
@endsection
