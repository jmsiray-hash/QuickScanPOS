@extends('layouts.app')

@section('title', 'Start Shopping | QuickScanPos')

@section('content')
    <section class="shop-panel">
        <h2>Scan &amp; Go</h2>
        @if ($activeCart)
            <section class="resume-cart" aria-label="Resume cart">
                <h3>Welcome back</h3>
                <p>You have an unfinished cart on this device. Continue where you left off, or start a new cart.</p>
                <div class="resume-summary">
                    <p><strong>Cart:</strong> {{ $activeCart->token }}</p>
                    <p><strong>Name:</strong> {{ $activeCart->customer_name }}</p>
                    <p><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $activeCart->status)) }}</p>
                    <p><strong>Items:</strong> {{ $activeCartData['total_pieces'] ?? 0 }}</p>
                    <p><strong>Total:</strong> ₱{{ number_format((float) ($activeCartData['grand_total'] ?? 0), 2) }}</p>
                </div>
                @if (!empty($activeCartData['items']))
                    <div class="resume-items">
                        <p><strong>What you ordered:</strong></p>
                        <ul>
                            @foreach ($activeCartData['items'] as $item)
                                <li>{{ $item['name'] }} × {{ $item['qty'] }} — ₱{{ number_format((float) $item['line_total'], 2) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="row-actions">
                    <form action="{{ route('shop.start') }}" method="POST">
                        @csrf
                        <input type="hidden" name="resume" value="1">
                        <button class="button" type="submit">Continue My Cart</button>
                    </form>
                </div>
            </section>
            <hr>
        @endif

        @if ($customer)
            <p>Signed in as <strong>{{ $customer->full_name }}</strong>. Continue with your account to save purchase history, or shop as a guest.</p>
            <div class="row-actions">
                <form action="{{ route('shop.start') }}" method="POST">
                    @csrf
                    <button class="button" type="submit">{{ $activeCart ? 'Start a New Account Cart' : 'Start Shopping with My Account' }}</button>
                </form>
                <form action="{{ route('shop.logout') }}" method="POST">
                    @csrf
                    <button class="button button-secondary" type="submit">Sign Out</button>
                </form>
            </div>
            <hr>
        @endif
        <h3>{{ $activeCart ? 'Start a New Guest Cart' : 'Continue as Guest' }}</h3>
        <p>No account or login is required to scan items and shop.</p>
        <form class="form-grid" action="{{ route('shop.start') }}" method="POST">
            @csrf
            <input type="hidden" name="guest" value="1">
            <div class="field">
                <label for="customer_name">Name for this checkout (optional)</label>
                <input id="customer_name" name="customer_name" maxlength="100" autocomplete="name" value="{{ old('customer_name') }}" placeholder="Guest Shopper">
                @error('customer_name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="button" type="submit">{{ $activeCart ? 'Start New Guest Cart' : 'Start Shopping as Guest' }}</button>
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
        .resume-cart { background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; margin:16px 0; padding:16px; }
        .resume-summary p, .resume-items p { margin:6px 0; }
        .resume-items ul { margin:8px 0 0; padding-left:20px; }
        .resume-items li { margin:4px 0; }
        @media(max-width:640px) { main { padding:20px; } .shop-panel .button { width:100%; } }
    </style>
@endsection
