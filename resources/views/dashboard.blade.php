@extends('layouts.app')

@section('title', 'QuickScanPos Dashboard')

@section('content')
    <section class="dashboard-heading">
        <p class="eyebrow">QUICKSCANPOS WORKSPACE</p>
        <h1>Welcome back, {{ auth()->user()->name }}.</h1>
        <p>Choose an action to continue managing your store.</p>
    </section>

    @if ($lowStockProducts->isNotEmpty())
        <section class="dashboard-stock-alert" aria-label="Low stock alert">
            <div><h2>Low stock alert</h2><p>{{ $lowStockCount }} active item(s) have five or fewer units.</p></div>
            <ul>
                @foreach ($lowStockProducts as $product)
                    <li>{{ $product->name }} <strong>{{ $product->stock_quantity }} left</strong></li>
                @endforeach
            </ul>
            <a class="button button-secondary" href="{{ route('products.index', ['stock' => 'low']) }}">Review low stock</a>
        </section>
    @endif

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
        .dashboard-stock-alert { background:#fffbeb; border:1px solid #fcd34d; border-radius:10px; display:grid; gap:10px; grid-template-columns:1fr 2fr auto; margin:0 0 24px; padding:16px; }
        .dashboard-stock-alert h2 { color:#92400e; font-size:1.15rem; margin:0; }
        .dashboard-stock-alert p { margin:4px 0 0; }
        .dashboard-stock-alert ul { display:flex; flex-wrap:wrap; gap:6px 18px; list-style:none; margin:0; padding:0; }
        .dashboard-stock-alert li { color:#78350f; }
        .dashboard-grid { display:grid; gap:16px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .dashboard-card { border:1px solid var(--line); border-radius:10px; color:inherit; display:flex; flex-direction:column; min-height:210px; padding:22px; text-decoration:none; transition:border-color .15s,transform .15s; }
        .dashboard-card:hover,.dashboard-card:focus { border-color:var(--brand); transform:translateY(-2px); }
        .dashboard-card-primary { background:var(--wash); border-color:#99f6e4; }
        .dashboard-card-label { color:var(--brand-dark); font-size:.72rem; font-weight:800; letter-spacing:.12em; }
        .dashboard-card h2 { font-size:1.2rem; margin:14px 0 4px; }
        .dashboard-card p { color:var(--muted); margin:0 0 16px; }
        .dashboard-card-action { color:var(--brand-dark); font-weight:700; margin-top:auto; }
        @media(max-width:760px) { .dashboard-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .dashboard-stock-alert { grid-template-columns:1fr; } }
        @media(max-width:520px) { .dashboard-grid { grid-template-columns:1fr; } }
    </style>
@endsection
