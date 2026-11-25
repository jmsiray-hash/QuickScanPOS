@extends('layouts.app')

@section('title', 'Start Shopping | QuickScanPos')

@section('content')
    <section class="shop-panel">
        <h2>Scan &amp; Go</h2>
        @if ($customer)
            <p>Signed in as <strong>{{ $customer->full_name }}</strong>. Continue with your account to save purchase history, or shop as a guest.</p>
            <div class="row-actions">
                <form action="{{ route('shop.start') }}" method="POST">
                    @csrf
                    <button class="button" type="submit">Start Shopping with My Account</button>
                </form>
                <form action="{{ route('shop.logout') }}" method="POST">
                    @csrf
                    <button class="button button-secondary" type="submit">Sign Out</button>
                </form>
            </div>
            <hr>
        @endif
        <h3>Continue as Guest</h3>
        <p>No account or login is required to scan items and shop.</p>
        <form class="form-grid" action="{{ route('shop.start') }}" method="POST">
            @csrf
            <input type="hidden" name="guest" value="1">
            <div class="field">
                <label for="customer_name">Name for this checkout (optional)</label>
                <input id="customer_name" name="customer_name" maxlength="100" autocomplete="name" value="{{ old('customer_name') }}" placeholder="Guest Shopper">
                @error('customer_name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="button" type="submit">Start Shopping as Guest</button>
        </form>
        @unless ($customer)
            <hr>
            <p>Want to save your purchase history? Sign in or create an account.</p>
            <form class="form-grid" action="{{ route('shop.login') }}" method="POST">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="100" required autocomplete="email" autofocus>
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password">
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="button" type="submit">Sign In</button>
            </form>
            <p>New customer? <a href="{{ route('shop.register') }}">Create an account</a>.</p>
        @endunless
    </section>
    <style>
        .shop-panel { margin:auto; max-width:620px; }
        @media(max-width:640px) { main { padding:20px; } .shop-panel .button { width:100%; } }
    </style>
@endsection
