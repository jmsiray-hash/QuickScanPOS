@extends('layouts.app')

@section('title', 'Create Customer Account | QuickScanPos')

@section('content')
    <section class="shop-panel">
        <h2>Create a Customer Account</h2>
        <p>Your account keeps Scan &amp; Go carts and purchase history together.</p>
        <form class="form-grid" action="{{ route('shop.register.store') }}" method="POST">
            @csrf
            <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" value="{{ old('full_name') }}" maxlength="100" autocomplete="name" required>
                @error('full_name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="100" autocomplete="email" required>
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="phone">Phone (optional)</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="20" autocomplete="tel">
                @error('phone')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Password (at least 8 characters)</label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="row-actions">
                <button class="button" type="submit">Create Account</button>
                <a class="button button-secondary" href="{{ route('shop.entry') }}">Back to Sign In</a>
            </div>
        </form>
    </section>
    <style>
        .shop-panel { margin:auto; max-width:620px; }
        @media(max-width:640px) { main { padding:20px; } .shop-panel .button { width:100%; } }
    </style>
@endsection
