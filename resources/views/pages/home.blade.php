@extends('layouts.app')

@section('title', 'QuickScanPos | Scan, Sell, Manage')

@section('content')
    <section class="quickpos-hero">
        <p class="eyebrow">QUICKSCANPOS · POINT OF SALE</p>
        <h1>Scan. Sell. Keep your store moving.</h1>
        <p class="hero-copy">Register products, scan barcodes or QR codes at checkout, and let customers build a Scan &amp; Go cart from their phones.</p>
        <div class="hero-actions">
            @auth
                <a class="button" href="{{ route('pos.index') }}">Open POS Checkout</a>
                <a class="button button-secondary" href="{{ route('products.create') }}">Register a Product</a>
            @else
                <a class="button" href="{{ route('login') }}">Cashier Sign In</a>
                <a class="button button-secondary" href="{{ route('shop.entry') }}">Start Scan &amp; Go</a>
            @endauth
        </div>
    </section>

    <section class="quickpos-features" aria-label="QuickScanPos features">
        <a class="feature-card" href="{{ route('shop.entry') }}">
            <span class="feature-icon" aria-hidden="true">01</span>
            <h2>Customer Scan &amp; Go</h2>
            <p>Customers scan items with a phone, manage their cart, and get a QR code for the cashier.</p>
            <span class="feature-link">Start shopping <span aria-hidden="true">&rarr;</span></span>
        </a>
        <a class="feature-card" href="{{ auth()->check() ? route('pos.index') : route('login') }}">
            <span class="feature-icon" aria-hidden="true">02</span>
            <h2>Fast POS Checkout</h2>
            <p>Scan product barcodes or QR codes with a camera or scanner, then complete checkout.</p>
            <span class="feature-link">{{ auth()->check() ? 'Open checkout' : 'Cashier sign in' }} <span aria-hidden="true">&rarr;</span></span>
        </a>
        <a class="feature-card" href="{{ auth()->check() ? route('products.create') : route('login') }}">
            <span class="feature-icon" aria-hidden="true">03</span>
            <h2>Product Registration</h2>
            <p>Add product details and stock, scan a code by camera or phone, and print a product QR.</p>
            <span class="feature-link">{{ auth()->check() ? 'Register product' : 'Cashier sign in' }} <span aria-hidden="true">&rarr;</span></span>
        </a>
    </section>

    <p class="quickpos-note">Phone camera scanning requires HTTPS or localhost. Barcode scanners that act as keyboards can be used on the cashier screen.</p>

    <style>
        .quickpos-hero { background:linear-gradient(125deg,#102c2a,#155e59); border-radius:14px; color:#fff; margin:-8px -8px 30px; padding:clamp(28px,6vw,64px); }
        .quickpos-hero h1 { color:#fff; max-width:760px; }
        .eyebrow { color:#99f6e4; font-size:.8rem; font-weight:800; letter-spacing:.16em; margin:0 0 14px; }
        .hero-copy { color:#d5eeea; font-size:1.08rem; max-width:720px; }
        .hero-actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:26px; }
        .quickpos-hero .button { background:#a7f3d0; color:#123b36; font-weight:700; }
        .quickpos-hero .button-secondary { background:#fff; color:#123b36; }
        .quickpos-features { display:grid; gap:16px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .feature-card { border:1px solid var(--line); border-radius:10px; color:inherit; display:flex; flex-direction:column; min-height:220px; padding:22px; text-decoration:none; transition:border-color .15s,transform .15s; }
        .feature-card:hover,.feature-card:focus { border-color:var(--brand); transform:translateY(-2px); }
        .feature-icon { align-items:center; background:var(--wash); border-radius:8px; color:var(--brand-dark); display:inline-flex; font-size:.85rem; font-weight:800; height:38px; justify-content:center; width:42px; }
        .feature-card h2 { font-size:1.15rem; margin:18px 0 4px; }
        .feature-card p { color:var(--muted); margin:0 0 18px; }
        .feature-link { color:var(--brand-dark); font-weight:700; margin-top:auto; }
        .quickpos-note { color:var(--muted); font-size:.9rem; margin:22px 0 0; }
        @media(max-width:760px) { .quickpos-features { grid-template-columns:1fr; } .quickpos-hero { margin:-8px -8px 22px; } }
    </style>
@endsection
