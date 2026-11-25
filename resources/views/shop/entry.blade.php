@extends('layouts.app')

@section('title', 'Start Shopping | QuickScanPos')

@section('content')
    <section class="shop-panel">
        <h2>Scan &amp; Go</h2>
        @if ($customer)
            <p>Signed in as <strong>{{ $customer->full_name }}</strong>. Start a cart and scan product barcodes as you shop.</p>
            <div class="row-actions">
                <form action="{{ route('shop.start') }}" method="POST">
                    @csrf
                    <button class="button" type="submit">Start Shopping</button>
                </form>
                <form action="{{ route('shop.logout') }}" method="POST">
                    @csrf
                    <button class="button button-secondary" type="submit">Sign Out</button>
                </form>
            </div>
        @else
            <p>Sign in or create an account to start a personal shopping cart and view your purchases.</p>
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
        @endif
    </section>
    <style>
        .shop-panel { margin:auto; max-width:620px; }
        @media(max-width:640px) { main { padding:20px; } .shop-panel .button { width:100%; } }
    </style>
@endsection
