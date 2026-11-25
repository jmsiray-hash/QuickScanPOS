@extends('layouts.app')

@section('title', 'QuickScanPos Dashboard')

@section('content')
    <section class="dashboard-heading">
        <p class="eyebrow">QUICKSCANPOS WORKSPACE</p>
        <h1>Welcome back, {{ auth()->user()->name }}.</h1>
        <p>Choose an action to continue managing your store.</p>
    </section>

    <section class="dashboard-grid" aria-label="Store management">
        <a class="dashboard-card dashboard-card-primary" href="{{ route('pos.index') }}">
            <span class="dashboard-card-label">SELL</span>
            <h2>POS Checkout</h2>
            <p>Scan product barcodes or QR codes, import a customer cart, and complete a sale.</p>
            <span class="dashboard-card-action">Open checkout &rarr;</span>
        </a>
        <a class="dashboard-card" href="{{ route('products.create') }}">
            <span class="dashboard-card-label">CATALOG</span>
            <h2>Register Product</h2>
            <p>Add a product and stock. Scan a barcode with the camera, a paired phone, or a scanner.</p>
            <span class="dashboard-card-action">Add product &rarr;</span>
        </a>
        <a class="dashboard-card" href="{{ route('products.index') }}">
            <span class="dashboard-card-label">CATALOG</span>
            <h2>Products &amp; QR Codes</h2>
            <p>Review stock, edit products, and print QR codes for registered products.</p>
            <span class="dashboard-card-action">View products &rarr;</span>
        </a>
        <a class="dashboard-card" href="{{ route('sales.index') }}">
            <span class="dashboard-card-label">REPORTS</span>
            <h2>Sales &amp; Sold Items</h2>
            <p>Review completed transactions, sold quantities, and revenue by date range.</p>
            <span class="dashboard-card-action">View sales reports &rarr;</span>
        </a>
        <a class="dashboard-card" href="{{ route('pos.store-qr') }}">
            <span class="dashboard-card-label">SCAN &amp; GO</span>
            <h2>Store Entry QR</h2>
            <p>Display or print a phone-accessible QR so customers can start shopping.</p>
            <span class="dashboard-card-action">Show store QR &rarr;</span>
        </a>
        <a class="dashboard-card" href="{{ route('customers.index') }}">
            <span class="dashboard-card-label">PEOPLE</span>
            <h2>Customers</h2>
            <p>Manage your customer records.</p>
            <span class="dashboard-card-action">View customers &rarr;</span>
        </a>
        <a class="dashboard-card" href="{{ route('users.index') }}">
            <span class="dashboard-card-label">TEAM</span>
            <h2>Users</h2>
            <p>Manage accounts for your store team.</p>
            <span class="dashboard-card-action">View users &rarr;</span>
        </a>
    </section>

    <style>
        .dashboard-heading { margin-bottom:28px; }
        .dashboard-heading .eyebrow { color:var(--brand); }
        .dashboard-heading h1 { font-size:clamp(2rem,4vw,2.7rem); }
        .dashboard-heading p:last-child { color:var(--muted); }
        .dashboard-grid { display:grid; gap:16px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .dashboard-card { border:1px solid var(--line); border-radius:10px; color:inherit; display:flex; flex-direction:column; min-height:210px; padding:22px; text-decoration:none; transition:border-color .15s,transform .15s; }
        .dashboard-card:hover,.dashboard-card:focus { border-color:var(--brand); transform:translateY(-2px); }
        .dashboard-card-primary { background:var(--wash); border-color:#99f6e4; }
        .dashboard-card-label { color:var(--brand-dark); font-size:.72rem; font-weight:800; letter-spacing:.12em; }
        .dashboard-card h2 { font-size:1.2rem; margin:14px 0 4px; }
        .dashboard-card p { color:var(--muted); margin:0 0 16px; }
        .dashboard-card-action { color:var(--brand-dark); font-weight:700; margin-top:auto; }
        @media(max-width:760px) { .dashboard-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:520px) { .dashboard-grid { grid-template-columns:1fr; } }
    </style>
@endsection
