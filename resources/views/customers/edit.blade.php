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
        <div class="field">
            <label for="password">New account password (leave blank to keep current)</label>
            <input id="password" name="password" type="password" minlength="8" autocomplete="new-password">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm new account password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="discount_id_type">Discount ID type</label>
            <select id="discount_id_type" name="discount_id_type">
                <option value="">None</option>
                <option value="pwd" @selected(old('discount_id_type', $customer->discount_id_type) === 'pwd')>PWD</option>
                <option value="senior" @selected(old('discount_id_type', $customer->discount_id_type) === 'senior')>Senior</option>
            </select>
            @error('discount_id_type')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="discount_id_number">Discount ID number</label>
            <input id="discount_id_number" name="discount_id_number" value="{{ old('discount_id_number', $customer->discount_id_number) }}" maxlength="100">
            @error('discount_id_number')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="monthly_discount_limit">Monthly store-discount quota (₱)</label>
            <input id="monthly_discount_limit" name="monthly_discount_limit" type="number" min="0" step="0.01" value="{{ old('monthly_discount_limit', $customer->monthly_discount_limit) }}" required>
            @error('monthly_discount_limit')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label><input type="checkbox" name="store_discount_reminder" value="1" @checked(old('store_discount_reminder', $customer->store_discount_reminder))> Remind cashier this customer usually asks for a store discount</label>
        </div>
        <div class="row-actions">
            <button class="button" type="submit">Update Customer</button>
            <a class="button button-secondary" href="{{ route('customers.index') }}">Cancel</a>
        </div>
    </form>
@endsection
