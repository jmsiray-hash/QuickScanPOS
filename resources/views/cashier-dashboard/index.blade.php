@extends('layouts.app')

@section('title', 'Cashier Dashboard | QuickScanPos')

@section('content')
    <section class="cashier-dashboard">
        <header class="cashier-dashboard-heading">
            <div>
                <p class="eyebrow">QUICKSCANPOS · CASHIER</p>
                <h1>Product Catalog</h1>
                <p>Search products, browse categories, and add items to the checkout cart.</p>
            </div>
            <a class="button" href="{{ route('pos.index') }}">Open Checkout</a>
        </header>

        <div class="catalog-layout">
            <aside class="catalog-sidebar">
                <h2>Categories</h2>
                <a class="category-link {{ $category === '' ? 'selected' : '' }}" href="{{ route('cashier-dashboard', ['search' => $search]) }}">All products</a>
                @foreach ($categories as $availableCategory)
                    <a class="category-link {{ $category === $availableCategory ? 'selected' : '' }}"
                       href="{{ route('cashier-dashboard', ['category' => $availableCategory, 'search' => $search]) }}">
                        {{ $availableCategory }}
                    </a>
                @endforeach
                <a class="catalog-qr-link" href="{{ route('pos.store-qr') }}">
                    <span aria-hidden="true">▦</span>
                    <strong>Store Entry QR</strong>
                    <small>Open the customer Scan &amp; Go QR</small>
                </a>
            </aside>

            <section class="catalog-content" aria-label="Available products">
                <form method="GET" action="{{ route('cashier-dashboard') }}" class="catalog-search">
                    @if ($category !== '')
                        <input type="hidden" name="category" value="{{ $category }}">
                    @endif
                    <label class="sr-only" for="catalog-search">Search products</label>
                    <input id="catalog-search" name="search" value="{{ $search }}" placeholder="Search by product name or code..." autocomplete="off">
                    <button class="button" type="submit">Search</button>
                </form>

                <div id="catalog-message" class="alert" role="status" hidden></div>
                <div class="product-grid">
                    @forelse ($products as $product)
                        <article class="catalog-product">
                            <a class="catalog-product-image" href="{{ route('products.edit', $product) }}" aria-label="Edit {{ $product->name }}">
                                @if ($product->image_path)
                                    <img src="{{ route('products.image', $product) }}" alt="{{ $product->name }}" loading="lazy"
                                         onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('span'),{textContent:'No photo'}));">
                                @else
                                    <span class="product-placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                                @endif
                            </a>
                            <div class="catalog-product-details">
                                <span class="catalog-category">{{ $product->category ?: 'Uncategorized' }}</span>
                                <h2>{{ $product->name }}</h2>
                                <p class="catalog-code">{{ $product->barcode }}</p>
                                <div class="catalog-product-bottom">
                                    <strong>₱{{ number_format($product->selling_price, 2) }}</strong>
                                    <small>{{ $product->stock_quantity }} in stock</small>
                                </div>
                                <div class="catalog-add-controls">
                                    <label class="sr-only" for="catalog-qty-{{ $product->id }}">Quantity for {{ $product->name }}</label>
                                    <input id="catalog-qty-{{ $product->id }}" class="catalog-quantity" type="number" min="1"
                                           max="{{ $product->stock_quantity }}" value="1" inputmode="numeric">
                                    <button class="button catalog-add-button" type="button" data-product-id="{{ $product->id }}"
                                            data-stock="{{ $product->stock_quantity }}">Add to Cart</button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="catalog-empty">No available products match this search or category.</p>
                    @endforelse
                </div>
                {{ $products->links() }}
            </section>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const message = document.getElementById('catalog-message');
            const cartQuantities = @json($cartQuantities);
            document.querySelectorAll('.catalog-add-button').forEach(button => {
                button.addEventListener('click', async () => {
                    const card = button.closest('.catalog-product');
                    const quantityInput = card.querySelector('.catalog-quantity');
                    const quantityToAdd = Number(quantityInput.value);
                    const stock = Number(button.dataset.stock);
                    if (!Number.isInteger(quantityToAdd) || quantityToAdd < 1) {
                        message.textContent = `Enter a whole quantity from 1 to ${stock}.`;
                        message.className = 'alert alert-errors';
                        message.hidden = false;
                        return;
                    }
                    const productId = Number(button.dataset.productId);
                    const quantity = Number(cartQuantities[productId] || 0) + quantityToAdd;
                    if (quantity > stock) {
                        message.textContent = `Only ${stock} unit(s) are currently in stock, including items already in the cart.`;
                        message.className = 'alert alert-errors';
                        message.hidden = false;
                        return;
                    }

                    button.disabled = true;
                    try {
                        const response = await fetch(@json(route('pos.cart.update')), {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                quantity: quantity,
                            }),
                        });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message || 'Product could not be added.');
                        cartQuantities[productId] = quantity;
                        message.innerHTML = `${quantity} item(s) added to cart. Cart total: <strong>₱${Number(result.cart.total).toFixed(2)}</strong>. <a href="{{ route('pos.index') }}">View cart / checkout</a>`;
                        message.className = 'alert alert-success';
                        message.hidden = false;
                    } catch (error) {
                        message.textContent = error.message || 'Product could not be added.';
                        message.className = 'alert alert-errors';
                        message.hidden = false;
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        });
    </script>

    <style>
        .cashier-dashboard { --catalog-ink:#f8fafc; --catalog-muted:#cbd5e1; --catalog-bg:#17212b; --catalog-panel:#24313c; }
        .cashier-dashboard-heading { align-items:center; background:linear-gradient(115deg,#102c2a,#155e59); border-radius:12px; color:#fff; display:flex; gap:20px; justify-content:space-between; margin-bottom:22px; padding:24px; }
        .cashier-dashboard-heading h1 { color:#fff; font-size:clamp(1.7rem,4vw,2.4rem); margin:0; }
        .cashier-dashboard-heading p:last-child { color:#d5eeea; margin:4px 0 0; }
        .cashier-dashboard-heading .eyebrow { color:#99f6e4; font-size:.75rem; font-weight:800; letter-spacing:.14em; margin:0 0 4px; }
        .catalog-layout { background:var(--catalog-bg); border-radius:12px; display:grid; gap:20px; grid-template-columns:205px minmax(0,1fr); min-height:560px; padding:18px; }
        .catalog-sidebar { display:flex; flex-direction:column; gap:8px; }
        .catalog-sidebar h2 { color:var(--catalog-ink); font-size:1.05rem; margin:0 0 4px; }
        .category-link { background:var(--catalog-panel); border:1px solid #3b4853; border-radius:7px; color:var(--catalog-ink); padding:9px 12px; text-decoration:none; transition:background .15s,border-color .15s; }
        .category-link:hover,.category-link.selected { background:#0f766e; border-color:#2dd4bf; color:#fff; }
        .catalog-qr-link { align-items:flex-start; background:#fff; border-radius:8px; color:#115e59; display:flex; flex-direction:column; gap:5px; margin-top:auto; padding:14px; text-decoration:none; }
        .catalog-qr-link span { font-size:2rem; }
        .catalog-qr-link small { color:#64748b; }
        .catalog-content { min-width:0; }
        .catalog-search { display:flex; gap:10px; margin:0 0 16px; }
        .catalog-search input { background:#fff; border:0; border-radius:999px; flex:1; font:inherit; min-width:0; padding:12px 18px; }
        .catalog-search button { border-radius:999px; min-width:110px; }
        .product-grid { display:grid; gap:12px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .catalog-product { background:#fff; border:1px solid #dbe5e3; border-radius:9px; color:#1f2937; min-width:0; overflow:hidden; }
        .catalog-product-image { align-items:center; background:#f1f5f9; display:flex; height:132px; justify-content:center; overflow:hidden; text-decoration:none; }
        .catalog-product-image img { height:100%; object-fit:cover; transition:transform .2s; width:100%; }
        .catalog-product:hover .catalog-product-image img { transform:scale(1.04); }
        .product-placeholder { align-items:center; background:#ccfbf1; border-radius:50%; color:#115e59; display:flex; font-size:2.5rem; font-weight:800; height:86px; justify-content:center; width:86px; }
        .catalog-product-details { padding:12px; }
        .catalog-category { color:#0f766e; font-size:.72rem; font-weight:800; text-transform:uppercase; }
        .catalog-product h2 { color:#1f2937; font-size:1rem; line-height:1.3; margin:4px 0; overflow-wrap:anywhere; }
        .catalog-code { color:#64748b; font-size:.76rem; margin:0; overflow-wrap:anywhere; }
        .catalog-product-bottom { align-items:baseline; display:flex; flex-wrap:wrap; gap:4px 10px; justify-content:space-between; margin:10px 0; }
        .catalog-product-bottom strong { color:#115e59; font-size:1.05rem; }
        .catalog-product-bottom small { color:#64748b; }
        .catalog-add-controls { display:grid; gap:7px; grid-template-columns:62px minmax(0,1fr); }
        .catalog-quantity { border:1px solid #cbd5e1; border-radius:6px; font:inherit; min-width:0; padding:7px; }
        .catalog-add-button { font-size:.86rem; padding:7px; }
        .catalog-add-button:disabled { cursor:wait; opacity:.65; }
        .catalog-empty { color:#fff; grid-column:1/-1; }
        .cashier-dashboard .pagination { color:#fff; }
        .cashier-dashboard .pagination a { color:#115e59; }
        @media(max-width:980px) { .product-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:700px) { .catalog-layout { grid-template-columns:1fr; } .catalog-sidebar { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); } .catalog-sidebar h2,.catalog-qr-link { grid-column:1/-1; } .catalog-qr-link { margin-top:8px; } }
        @media(max-width:480px) { .product-grid { grid-template-columns:1fr; } .catalog-product { display:grid; grid-template-columns:112px minmax(0,1fr); } .catalog-product-image { height:100%; min-height:190px; } .cashier-dashboard-heading { align-items:flex-start; flex-direction:column; } .catalog-search { flex-direction:column; } .catalog-search button { min-height:44px; } }
    </style>
@endsection
