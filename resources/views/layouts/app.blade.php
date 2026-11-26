<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QuickScanPos')</title>
    <style>
        :root { --ink:#1f2937; --muted:#64748b; --brand:#0f766e; --brand-dark:#115e59; --line:#dbe5e3; --surface:#fff; --wash:#f0fdfa; }
        * { box-sizing:border-box; } body { background:#f7faf9; color:var(--ink); font-family:Arial,sans-serif; line-height:1.6; margin:0; }
        .site-shell { margin:0 auto; max-width:1120px; padding:24px; } .site-header { align-items:center; display:flex; gap:24px; justify-content:space-between; margin-bottom:36px; }
        .brand { color:var(--brand-dark); font-size:1.25rem; font-weight:800; letter-spacing:-.03em; text-decoration:none; } nav { display:flex; flex-wrap:wrap; gap:6px; align-items:center; } nav a { border-radius:6px; color:var(--muted); padding:8px 10px; text-decoration:none; }
        nav a:hover, nav a:focus { background:var(--wash); color:var(--brand-dark); } main { background:var(--surface); border:1px solid var(--line); border-radius:8px; padding:clamp(24px,5vw,56px); }
        h1 { color:var(--brand-dark); font-size:clamp(2rem,5vw,3.5rem); line-height:1.1; margin:0 0 16px; } h2 { color:var(--brand-dark); margin-top:0; }
        table { border-collapse:collapse; margin-top:20px; min-width:680px; width:100%; } .table-wrap { overflow-x:auto; } th,td { border-bottom:1px solid var(--line); padding:14px 12px; text-align:left; } th { background:var(--wash); color:var(--brand-dark); font-size:.85rem; text-transform:uppercase; }
        .page-actions,.row-actions { align-items:center; display:flex; gap:10px; } .page-actions { justify-content:space-between; } .button { background:var(--brand); border:0; border-radius:6px; color:#fff; cursor:pointer; display:inline-block; font:inherit; padding:9px 14px; text-decoration:none; }
        .button-secondary { background:#e2e8f0; color:var(--ink); } .form-grid { display:grid; gap:18px; max-width:640px; } .field { display:grid; gap:6px; } .field label { font-weight:700; } .field input { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:10px 12px; width:100%; }
        .error { color:#b91c1c; margin:0; } .alert { border-radius:6px; margin-bottom:20px; padding:12px 16px; } .alert-success { background:#dcfce7; color:#166534; } .alert-errors { background:#fee2e2; color:#991b1b; }
        .site-header nav { justify-content:flex-end; } .site-header nav form { display:inline; }
        .site-header nav .cashier-dashboard-link { background:var(--brand); border-radius:6px; color:#fff; font-weight:700; }
        .site-header nav .cashier-dashboard-link:hover,.site-header nav .cashier-dashboard-link:focus { background:var(--brand-dark); color:#fff; }
        .avatar { border-radius:50%; display:block; height:56px; object-fit:cover; width:56px; } .avatar-preview { height:120px; width:120px; } dt { font-weight:700; margin-top:16px; } dd { margin-left:0; }
        footer { color:var(--muted); font-size:.9rem; padding:20px 0; } @media(max-width:640px) { .site-header { align-items:flex-start; flex-direction:column; gap:12px; } .site-shell { padding:16px; } }
        @media(max-width:900px) { .site-header { align-items:flex-start; flex-direction:column; } .site-header nav { justify-content:flex-start; } }
    </style>
</head>
<body class="{{ request()->routeIs('cashier-dashboard') ? 'cashier-app-body' : '' }}">
<div class="site-shell {{ request()->routeIs('cashier-dashboard') ? 'cashier-app-shell' : '' }}">
    @if (request()->routeIs('cashier-dashboard'))
        <header class="cashier-app-header">
            <a class="cashier-back-button" href="{{ route('dashboard') }}"><span aria-hidden="true">←</span> Back to dashboard</a>
        </header>
    @else
        <header class="site-header">
            <a class="brand" href="{{ route('home') }}">QuickScanPos</a>
            <nav aria-label="Primary navigation">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('shop.entry') }}">Scan &amp; Go</a>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a class="cashier-dashboard-link" href="{{ route('cashier-dashboard') }}">Cashier Dashboard</a>
                <a href="{{ route('pos.index') }}">Checkout</a>
                <a href="{{ route('products.index') }}">Products</a>
                <a href="{{ route('sales.index') }}">Sales Reports</a>
                <a href="{{ route('customers.index') }}">Customers</a>
                <a href="{{ route('customer-activity.index') }}">Scan &amp; Go Customers</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('settings.store.edit') }}">Settings</a>
                @endif
                <a href="{{ route('users.index') }}">Users</a>
                <form action="{{ route('logout') }}" method="POST">@csrf<button class="button button-secondary" type="submit">Log Out</button></form>
            @else
                <a href="{{ route('login') }}">Cashier Sign In</a>
                <a class="button" href="{{ route('register') }}">Create Account</a>
            @endauth
            </nav>
        </header>
    @endif
    <main class="{{ request()->routeIs('cashier-dashboard') ? 'cashier-app-main' : '' }}">
        @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-errors" role="alert"><p>Please correct the following errors:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>
    @unless (request()->routeIs('cashier-dashboard'))
        <footer><p>&copy; {{ date('Y') }} QuickScanPos · Scan, sell, manage.</p></footer>
    @endunless
</div>
</body>
</html>
