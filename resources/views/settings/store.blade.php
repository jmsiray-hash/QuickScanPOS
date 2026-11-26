@extends('layouts.app')

@section('title', 'Store Settings | QuickScanPos')

@section('content')
    <h2>Company Store Settings</h2>
    <p>These details appear on printed receipts and the receipt customers see after checkout.</p>
    <form method="POST" action="{{ route('settings.store.update') }}" class="form-grid">
        @csrf
        @method('PUT')
        <div class="field">
            <label for="store-name">Company / store name</label>
            <input id="store-name" name="store_name" value="{{ old('store_name', $settings->store_name) }}" maxlength="150" required>
            @error('store_name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="store-address">Company address</label>
            <textarea id="store-address" name="store_address" rows="4" maxlength="1000" required>{{ old('store_address', $settings->store_address) }}</textarea>
            @error('store_address')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="button" type="submit">Save Store Settings</button>
    </form>
    <style>
        #store-address { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:10px 12px; resize:vertical; width:100%; }
    </style>
@endsection
