<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QuickScanPos')</title>
    <style>
        :root { --ink:#1f2937; --muted:#64748b; --brand:#0f766e; --brand-dark:#115e59; --line:#dbe5e3; --surface:#fff; --wash:#f0fdfa; }
        * { box-sizing:border-box; } body { background:#f1f5f2; color:var(--ink); font-family:Arial,sans-serif; line-height:1.6; margin:0; }
        .site-shell { display:grid; grid-template-columns:246px minmax(0,1fr); min-height:100vh; } .site-content { display:flex; flex-direction:column; min-width:0; padding:28px 32px; }
        .site-sidebar { background:#102a20; color:#d5e4d9; display:flex; flex-direction:column; gap:26px; min-height:100vh; padding:28px 16px 18px; }
        .sidebar-brand { align-items:center; color:#f3f8f3; display:flex; font-size:1.05rem; font-weight:800; gap:12px; letter-spacing:-.02em; text-decoration:none; }
        .sidebar-brand-mark { background:#fff; border:1px solid rgba(255,255,255,.2); border-radius:11px; display:block; flex:none; height:42px; object-fit:cover; width:42px; }
        .sidebar-context { background:rgba(255,255,255,.055); border:1px solid rgba(255,255,255,.09); border-radius:12px; padding:12px; }
        .sidebar-context span { color:#91ad9d; display:block; font-size:.62rem; letter-spacing:.12em; text-transform:uppercase; }
        .sidebar-context strong { color:#f1f8f2; display:block; font-size:.86rem; margin-top:3px; overflow-wrap:anywhere; }
        .site-sidebar nav { align-content:start; display:grid; gap:6px; }
        .site-sidebar nav a { align-items:center; border-left:3px solid transparent; border-radius:8px; color:#b5c9bc; display:flex; font-size:.88rem; gap:11px; min-height:44px; padding:9px 11px; text-decoration:none; transition:background .15s,color .15s; }
        .site-sidebar nav a:hover,.site-sidebar nav a:focus { background:rgba(255,255,255,.075); color:#fff; }
        .site-sidebar nav a.is-active { background:#20392f; border-left-color:#16a575; color:#fff; font-weight:700; }
        .sidebar-nav-icon { color:#9bb9a7; flex:none; font-size:1rem; text-align:center; width:18px; }
        .site-sidebar nav form { margin-top:auto; }
        .sidebar-logout { align-items:center; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12); border-radius:8px; color:#f2f6f3; cursor:pointer; display:flex; font:inherit; font-size:.86rem; gap:11px; min-height:44px; padding:9px 12px; text-align:left; width:100%; }
        .sidebar-logout:hover { background:rgba(255,255,255,.14); }
        main { background:var(--surface); border:1px solid var(--line); border-radius:12px; flex:1; min-width:0; padding:clamp(24px,4vw,48px); }
        h1 { color:var(--brand-dark); font-size:clamp(2rem,5vw,3.5rem); line-height:1.1; margin:0 0 16px; } h2 { color:var(--brand-dark); margin-top:0; }
        table { border-collapse:collapse; margin-top:20px; min-width:680px; width:100%; } .table-wrap { overflow-x:auto; } th,td { border-bottom:1px solid var(--line); padding:14px 12px; text-align:left; } th { background:var(--wash); color:var(--brand-dark); font-size:.85rem; text-transform:uppercase; }
        .page-actions,.row-actions { align-items:center; display:flex; gap:10px; } .page-actions { justify-content:space-between; } .button { background:var(--brand); border:0; border-radius:6px; color:#fff; cursor:pointer; display:inline-block; font:inherit; padding:9px 14px; text-decoration:none; }
        .button-secondary { background:#e2e8f0; color:var(--ink); } .form-grid { display:grid; gap:18px; max-width:640px; } .field { display:grid; gap:6px; } .field label { font-weight:700; } .field input { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:10px 12px; width:100%; }
        .error { color:#b91c1c; margin:0; } .alert { border-radius:6px; margin-bottom:20px; padding:12px 16px; } .alert-success { background:#dcfce7; color:#166534; } .alert-errors { background:#fee2e2; color:#991b1b; }
        .avatar { border-radius:50%; display:block; height:56px; object-fit:cover; width:56px; } .avatar-preview { height:120px; width:120px; } dt { font-weight:700; margin-top:16px; } dd { margin-left:0; }
        footer { color:var(--muted); font-size:.9rem; padding:20px 0 0; }
        @media(max-width:820px) {
            .site-shell { grid-template-columns:190px minmax(0,1fr); }
            .site-sidebar { padding-left:12px; padding-right:12px; }
            .site-content { padding:20px; }
        }
        @media(max-width:640px) {
            .site-shell { display:flex; flex-direction:column; }
            .site-sidebar { gap:12px; min-height:auto; padding:12px; }
            .sidebar-brand { font-size:.95rem; }
            .sidebar-brand-mark { height:34px; width:34px; }
            .sidebar-context { display:none; }
            .site-sidebar nav { display:flex; gap:4px; overflow-x:auto; padding-bottom:3px; }
            .site-sidebar nav a { border-left:0; border-radius:7px; flex:none; font-size:.76rem; min-height:38px; padding:7px 9px; }
            .site-sidebar nav a.is-active { border-left:0; box-shadow:inset 0 -2px #16a575; }
            .site-sidebar nav form { flex:none; margin:0; }
            .sidebar-logout { min-height:38px; padding:7px 9px; width:auto; }
            .site-content { padding:14px; }
        }
    </style>
</head>
<body class="{{ request()->routeIs('cashier-dashboard') ? 'cashier-app-body' : '' }}{{ request()->routeIs('dashboard') ? ' dashboard-page' : '' }}">
<div class="site-shell {{ request()->routeIs('cashier-dashboard') ? 'cashier-app-shell' : '' }}">
    <aside class="site-sidebar">
        <a class="sidebar-brand" href="{{ auth()->check() && ! auth()->user()->isAdmin() ? route('cashier-dashboard') : route('home') }}"><img class="sidebar-brand-mark" src="{{ asset('images/quickscanpos-logo.png') }}" alt="">QuickScanPos</a>
        @auth
            <div class="sidebar-context">
                <span>Signed in as</span>
                <strong>{{ auth()->user()->full_name ?: auth()->user()->username }}</strong>
            </div>
        @endauth
        <nav aria-label="Primary navigation">
            @auth
                @if (auth()->user()->role !== 'cashier')
                    <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">⌂</span>Home</a>
                    <a class="{{ request()->routeIs('shop.*') ? 'is-active' : '' }}" href="{{ route('shop.entry') }}" @if (request()->routeIs('shop.*')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">▦</span>Scan &amp; Go</a>
                    <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">▤</span>Dashboard</a>
                    <a class="{{ request()->routeIs('cashier-dashboard', 'pos.index') ? 'is-active' : '' }}" href="{{ route('cashier-dashboard') }}" @if (request()->routeIs('cashier-dashboard', 'pos.index')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">▣</span>QuickScan POS Register</a>
                    <a class="{{ request()->routeIs('sales.*') ? 'is-active' : '' }}" href="{{ route('sales.index') }}" @if (request()->routeIs('sales.*')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">₱</span>Sales Reports</a>
                    <a class="{{ request()->routeIs('customers.*') ? 'is-active' : '' }}" href="{{ route('customers.index') }}" @if (request()->routeIs('customers.*')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">♙</span>Customers</a>
                    <a class="{{ request()->routeIs('customer-activity.*') ? 'is-active' : '' }}" href="{{ route('customer-activity.index') }}" @if (request()->routeIs('customer-activity.*')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">◉</span>Scan &amp; Go Customers</a>
                    <a class="{{ request()->routeIs('ratings.*') ? 'is-active' : '' }}" href="{{ route('ratings.index') }}" @if (request()->routeIs('ratings.index')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">★</span>Ratings</a>
                    @if (auth()->user()->isAdmin())
                        <a class="{{ request()->routeIs('settings.store.*') ? 'is-active' : '' }}" href="{{ route('settings.store.edit') }}" @if (request()->routeIs('settings.store.*')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">⚙</span>Company Settings</a>
                        <a class="{{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}" @if (request()->routeIs('users.*')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">♟</span>Users</a>
                    @endif
                @else
                    <a class="{{ request()->routeIs('cashier-dashboard', 'pos.index') ? 'is-active' : '' }}" href="{{ route('cashier-dashboard') }}" @if (request()->routeIs('cashier-dashboard', 'pos.index')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">▣</span>QuickScan POS Register</a>
                @endif
                <form action="{{ route('logout') }}" method="POST">@csrf<button class="sidebar-logout" type="submit"><span class="sidebar-nav-icon" aria-hidden="true">↪</span>Log Out</button></form>
            @else
                <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">⌂</span>Home</a>
                <a class="{{ request()->routeIs('shop.*') ? 'is-active' : '' }}" href="{{ route('shop.entry') }}" @if (request()->routeIs('shop.*')) aria-current="page" @endif><span class="sidebar-nav-icon" aria-hidden="true">▦</span>Scan &amp; Go</a>
                <a href="{{ route('login') }}"><span class="sidebar-nav-icon" aria-hidden="true">↪</span>Cashier Sign In</a>
            @endauth
        </nav>
    </aside>
    <div class="site-content">
    <main class="{{ request()->routeIs('cashier-dashboard') ? 'cashier-app-main' : '' }}{{ request()->routeIs('dashboard') ? ' dashboard-main' : '' }}">
        @unless (request()->routeIs('cashier-dashboard'))
            @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="alert alert-errors" role="alert"><p>Please correct the following errors:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        @endunless
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>
        <footer><p>&copy; {{ date('Y') }} QuickScanPos · Scan, sell, manage.</p></footer>
    </div>
</div>
</body>
</html>
