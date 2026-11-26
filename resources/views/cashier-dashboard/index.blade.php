@extends('layouts.app')

@section('title', 'Cashier POS | QuickScanPos')

@section('content')
    <section class="cashier-app">
        <header class="cashier-topbar">
            <div>
                <p class="cashier-kicker">QUICKSCAN POS · REGISTER</p>
                <h1>Cashier workspace</h1>
            </div>
            <div class="cashier-shift-state {{ $activeShift ? 'is-open' : 'is-closed' }}">
                <span class="shift-dot"></span>
                {{ $activeShift ? 'SHIFT OPEN' : 'SHIFT NOT OPEN' }}
            </div>
        </header>

        @if (!$activeShift)
            <form method="POST" action="{{ route('pos.shift.open') }}" class="shift-open-form">
                @csrf
                <input type="hidden" name="cashier_dashboard" value="1">
                <div>
                    <strong>Open your cashier shift</strong>
                    <p>Enter the opening cash fund before accepting payments.</p>
                </div>
                <label for="cashier-opening-fund">Opening fund (₱)</label>
                <input id="cashier-opening-fund" name="opening_cash" type="number" step="0.01" min="0" required>
                <button class="cashier-primary-button" type="submit">Open Shift</button>
            </form>
        @else
            <div class="shift-open-banner">
                Shift started {{ $activeShift->opened_at->format('M j, g:i A') }} · Opening fund ₱{{ number_format($activeShift->opening_cash, 2) }}
            </div>
        @endif

        <div id="cashier-notice" class="cashier-notice" role="status" hidden></div>

        <div class="cashier-workspace">
            <section class="catalog-pane">
                <div class="catalog-toolbar">
                    <form method="GET" action="{{ route('cashier-dashboard') }}" class="catalog-search">
                        @if ($category !== '')
                            <input type="hidden" name="category" value="{{ $category }}">
                        @endif
                        <label class="sr-only" for="catalog-search">Search products</label>
                        <input id="catalog-search" name="search" value="{{ $search }}" placeholder="Search product name or barcode..." autocomplete="off">
                        <button class="cashier-primary-button" type="submit">Search</button>
                    </form>
                    <a class="store-qr-shortcut" href="{{ route('pos.store-qr') }}" aria-label="Show store entrance QR">▦</a>
                </div>
                <nav class="category-tabs" aria-label="Product categories">
                    <a class="{{ $category === '' ? 'selected' : '' }}" href="{{ route('cashier-dashboard', ['search' => $search]) }}">All products</a>
                    @foreach ($categories as $availableCategory)
                        <a class="{{ $category === $availableCategory ? 'selected' : '' }}"
                           href="{{ route('cashier-dashboard', ['category' => $availableCategory, 'search' => $search]) }}">
                            {{ $availableCategory }}
                        </a>
                    @endforeach
                </nav>

                <div class="product-grid">
                    @forelse ($products as $product)
                        <article class="catalog-product">
                            <div class="catalog-product-image">
                                @if ($product->image_path)
                                    <img src="{{ route('products.image', $product) }}" alt="{{ $product->name }}" loading="lazy"
                                         onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('span'),{textContent:'No photo'}));">
                                @else
                                    <span class="product-placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                                @endif
                            </div>
                            <div class="catalog-product-details">
                                <span class="catalog-category">{{ $product->category ?: 'Uncategorized' }}</span>
                                <h2>{{ $product->name }}</h2>
                                @if ($product->senior_pwd_eligible)
                                    <span class="eligible-badge">Senior/PWD eligible</span>
                                @endif
                                <p class="catalog-code">{{ $product->barcode }}</p>
                                <div class="catalog-product-bottom">
                                    <strong>₱{{ number_format($product->selling_price, 2) }}</strong>
                                    <small>{{ $product->stock_quantity }} available</small>
                                </div>
                                <button class="add-product-button" type="button" data-product-id="{{ $product->id }}"
                                        data-stock="{{ $product->stock_quantity }}" data-price="{{ $product->selling_price }}">
                                    <span aria-hidden="true">＋</span> Add to cart
                                </button>
                            </div>
                        </article>
                    @empty
                        <p class="catalog-empty">No available products match this search or category.</p>
                    @endforelse
                </div>
                {{ $products->links() }}
            </section>

            <aside class="cart-pane" aria-label="Current sale">
                <div class="cart-heading">
                    <div><p class="cashier-kicker">CURRENT SALE</p><h2>Cart <span id="cart-item-count">0</span></h2></div>
                    <button id="clear-cart" class="text-button" type="button">Clear</button>
                </div>
                <div id="cart-empty" class="cart-empty">
                    <span aria-hidden="true">▤</span>
                    <strong>Your cart is empty</strong>
                    <small>Select a product to start a sale.</small>
                </div>
                <div id="cart-lines" class="cart-lines"></div>
                <div class="cart-calculations">
                    <div><span>Subtotal</span><strong id="cart-subtotal">₱0.00</strong></div>
                    <div><span>Discount</span><strong id="cart-discount">−₱0.00</strong></div>
                    <div><span>Tax</span><strong id="cart-tax">₱0.00</strong></div>
                    <div class="cart-grand-total"><span>Total due</span><strong id="cart-total">₱0.00</strong></div>
                </div>

                <button id="open-discount" class="discount-trigger" type="button" aria-expanded="false">
                    <span aria-hidden="true">◇</span> Apply discount
                </button>
                <section id="discount-panel" class="discount-panel" hidden>
                    <label for="discount-type">Discount type</label>
                    <select id="discount-type">
                        <option value="">No special discount</option>
                        <option value="senior">Senior Citizen · 20%</option>
                        <option value="pwd">PWD · 20%</option>
                    </select>
                    <label for="discount-id">ID number</label>
                    <input id="discount-id" type="text" maxlength="100" placeholder="Enter Senior/PWD ID">
                    <small>Discount applies to catalog-eligible items only. PROMO items are excluded.</small>
                </section>

                <section class="payment-section">
                    <h3>Payment method</h3>
                    <div class="payment-options" role="group" aria-label="Payment method">
                        <button type="button" class="payment-option selected" data-method="cash">Cash</button>
                        <button type="button" class="payment-option" data-method="gcash">GCash</button>
                        <button type="button" class="payment-option" data-method="maya">Maya</button>
                        <button type="button" class="payment-option" data-method="card">Card</button>
                    </div>
                    <div id="cash-payment-fields" class="payment-detail">
                        <label for="cash-received">Cash received (₱)</label>
                        <input id="cash-received" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                        <p id="cash-change">Change: ₱0.00</p>
                    </div>
                    <div id="wallet-payment-fields" class="payment-detail" hidden>
                        <label for="payment-reference">Payment reference number</label>
                        <input id="payment-reference" type="text" maxlength="100" autocomplete="off" placeholder="Enter reference number">
                    </div>
                    <button id="complete-sale" class="complete-sale-button" type="button" @disabled(!$activeShift)>
                        Complete payment · ₱<span id="payment-total">0.00</span>
                    </button>
                    @unless ($activeShift)
                        <p class="shift-required-note">Open your shift to enable payment.</p>
                    @endunless
                </section>
                <p class="cart-footnote">Payments are recorded to your open cashier shift.</p>
            </aside>
        </div>

        <dialog id="sale-complete-dialog" class="sale-dialog">
            <div class="sale-dialog-icon">✓</div>
            <h2>Payment complete</h2>
            <p id="sale-complete-message"></p>
            <div class="sale-dialog-actions">
                <a id="sale-receipt-link" class="cashier-secondary-button" href="#">View receipt</a>
                <button id="close-sale-dialog" class="cashier-primary-button" type="button">Continue</button>
            </div>
        </dialog>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const notice = document.getElementById('cashier-notice');
            const cartLines = document.getElementById('cart-lines');
            const emptyState = document.getElementById('cart-empty');
            const vatRate = @json((float) config('pos.vat_rate', 0));
            const hasOpenShift = @json($activeShift !== null);
            let cart = null;
            let paymentMethod = 'cash';
            let submitting = false;

            const money = value => '₱' + Number(value || 0).toFixed(2);
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            })[char]);
            async function request(url, method = 'GET', data = null) {
                const options = { method, headers: { Accept: 'application/json' } };
                if (method !== 'GET') {
                    options.headers['Content-Type'] = 'application/json';
                    options.headers['X-CSRF-TOKEN'] = csrf;
                    options.body = JSON.stringify(data || {});
                }
                const response = await fetch(url, options);
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
                return result;
            }
            function inform(text, isError = false) {
                notice.textContent = text;
                notice.className = 'cashier-notice ' + (isError ? 'is-error' : 'is-success');
                notice.hidden = false;
            }
            function paymentTotals() {
                if (!cart) return { total: 0, discount: 0, tax: 0 };
                let total = Number(cart.total || 0);
                let discount = Number(cart.discount || 0);
                let tax = Number(cart.tax || 0);
                if (document.getElementById('discount-type').value) {
                    const divider = 1 + Number(vatRate || 0) / 100;
                    for (const item of cart.items) {
                        if (!item.senior_pwd_eligible || item.is_promo) continue;
                        const base = Number(item.unit_price) * Number(item.quantity) / divider;
                        const eligibleTotal = Math.round(base * 0.8 * 100) / 100;
                        total -= Number(item.line_total) - eligibleTotal;
                        discount -= Number(item.discount_amount || 0);
                        discount += Math.round(base * 0.2 * 100) / 100;
                        tax -= Number(item.tax_amount || 0);
                    }
                }
                return {
                    total: Math.max(0, Math.round(total * 100) / 100),
                    discount: Math.max(0, Math.round(discount * 100) / 100),
                    tax: Math.max(0, Math.round(tax * 100) / 100),
                };
            }
            function updatePaymentState() {
                const due = paymentTotals().total;
                const cash = Number(document.getElementById('cash-received').value || 0);
                const discountType = document.getElementById('discount-type').value;
                const discountId = document.getElementById('discount-id').value.trim();
                const hasEligibleItems = Boolean(cart?.items?.some(item => item.senior_pwd_eligible && !item.is_promo));
                document.getElementById('payment-total').textContent = due.toFixed(2);
                document.getElementById('cash-change').textContent = 'Change: ' + money(Math.max(0, cash - due));
                const ready = paymentMethod === 'cash'
                    ? cash >= due && due > 0
                    : (!['gcash', 'maya'].includes(paymentMethod) || document.getElementById('payment-reference').value.trim() !== '');
                document.getElementById('complete-sale').disabled =
                    !hasOpenShift || !cart?.items?.length || !ready
                    || (discountType !== '' && (!discountId || !hasEligibleItems)) || submitting;
                document.getElementById('discount-id').disabled = discountType === '' || !hasEligibleItems;
                document.getElementById('discount-id').placeholder = hasEligibleItems
                    ? 'Enter Senior/PWD ID'
                    : 'No eligible item in cart';
            }
            function renderCart(nextCart) {
                cart = nextCart;
                const items = cart.items || [];
                emptyState.hidden = items.length > 0;
                cartLines.innerHTML = items.map(item => `
                    <article class="cart-line">
                        <div class="cart-line-main">
                            <strong>${escapeHtml(item.name)}${item.is_promo ? ' <span class="promo-badge">PROMO</span>' : ''}</strong>
                            <small>${escapeHtml(item.barcode)} · ${money(item.unit_price)} each</small>
                            <div class="cart-line-controls">
                                <button type="button" class="quantity-button" data-action="minus" data-id="${item.product_id}" aria-label="Decrease quantity">−</button>
                                <span>${item.quantity}</span>
                                <button type="button" class="quantity-button" data-action="plus" data-id="${item.product_id}" aria-label="Increase quantity">+</button>
                                <button type="button" class="remove-line" data-id="${item.product_id}">Remove</button>
                            </div>
                        </div>
                        <strong class="cart-line-total">${money(item.line_total)}</strong>
                    </article>
                `).join('');
                document.getElementById('cart-item-count').textContent = String(items.reduce((count, item) => count + Number(item.quantity), 0));
                const totals = paymentTotals();
                document.getElementById('cart-subtotal').textContent = money(cart.subtotal);
                document.getElementById('cart-discount').textContent = '−' + money(totals.discount);
                document.getElementById('cart-tax').textContent = money(totals.tax);
                document.getElementById('cart-total').textContent = money(totals.total);
                updatePaymentState();
            }
            async function refreshCart() {
                const result = await request(@json(route('pos.cart.contents')));
                renderCart(result.cart);
            }
            async function setQuantity(productId, quantity) {
                try {
                    const result = await request(@json(route('pos.cart.update')), 'POST', {
                        product_id: Number(productId),
                        quantity: Number(quantity),
                    });
                    renderCart(result.cart);
                } catch (error) {
                    inform(error.message, true);
                }
            }

            document.querySelectorAll('.add-product-button').forEach(button => {
                button.addEventListener('click', async () => {
                    const productId = Number(button.dataset.productId);
                    const item = cart?.items?.find(line => Number(line.product_id) === productId);
                    const quantity = Number(item?.quantity || 0) + 1;
                    if (quantity > Number(button.dataset.stock)) {
                        inform('This item has reached its available stock limit.', true);
                        return;
                    }
                    button.disabled = true;
                    try {
                        const result = await request(@json(route('pos.cart.update')), 'POST', {
                            product_id: productId,
                            quantity,
                        });
                        renderCart(result.cart);
                        inform('Added to cart: ' + button.closest('.catalog-product').querySelector('h2').textContent);
                    } catch (error) {
                        inform(error.message, true);
                    } finally {
                        button.disabled = false;
                    }
                });
            });
            cartLines.addEventListener('click', event => {
                const button = event.target.closest('button[data-id]');
                if (!button || !cart) return;
                const item = cart.items.find(line => Number(line.product_id) === Number(button.dataset.id));
                if (!item) return;
                if (button.classList.contains('remove-line')) {
                    setQuantity(item.product_id, 0);
                } else {
                    const nextQuantity = Number(item.quantity) + (button.dataset.action === 'plus' ? 1 : -1);
                    if (nextQuantity < 1 || nextQuantity > Number(item.available_stock)) return;
                    setQuantity(item.product_id, nextQuantity);
                }
            });
            document.getElementById('clear-cart').addEventListener('click', async () => {
                if (!cart?.items?.length || !window.confirm('Remove all items from this cart?')) return;
                for (const item of [...cart.items]) {
                    const result = await request(@json(route('pos.cart.update')), 'POST', {
                        product_id: Number(item.product_id),
                        quantity: 0,
                    });
                    cart = result.cart;
                }
                renderCart(cart);
                inform('Cart cleared.');
            });

            document.getElementById('open-discount').addEventListener('click', event => {
                const panel = document.getElementById('discount-panel');
                panel.hidden = !panel.hidden;
                event.currentTarget.setAttribute('aria-expanded', String(!panel.hidden));
                event.currentTarget.classList.toggle('is-active', !panel.hidden);
            });
            document.getElementById('discount-type').addEventListener('change', updatePaymentState);
            document.getElementById('discount-id').addEventListener('input', updatePaymentState);
            document.querySelectorAll('.payment-option').forEach(button => {
                button.addEventListener('click', () => {
                    paymentMethod = button.dataset.method;
                    document.querySelectorAll('.payment-option').forEach(option => option.classList.toggle('selected', option === button));
                    document.getElementById('cash-payment-fields').hidden = paymentMethod !== 'cash';
                    document.getElementById('wallet-payment-fields').hidden = !['gcash', 'maya'].includes(paymentMethod);
                    updatePaymentState();
                });
            });
            document.getElementById('cash-received').addEventListener('input', updatePaymentState);
            document.getElementById('payment-reference').addEventListener('input', updatePaymentState);
            document.getElementById('complete-sale').addEventListener('click', async () => {
                if (submitting) return;
                submitting = true;
                updatePaymentState();
                try {
                    const result = await request(@json(route('pos.checkout')), 'POST', {
                        payment_method: paymentMethod,
                        cash_received: paymentMethod === 'cash' ? document.getElementById('cash-received').value : null,
                        payment_reference: ['gcash', 'maya'].includes(paymentMethod)
                            ? document.getElementById('payment-reference').value.trim()
                            : null,
                        special_discount_type: document.getElementById('discount-type').value || null,
                        special_discount_id_number: document.getElementById('discount-id').value.trim() || null,
                    });
                    renderCart(result.cart);
                    document.getElementById('sale-complete-message').textContent = `Sale #${result.sale_id} · Paid ${money(result.receipt.total)} by ${paymentMethod.toUpperCase()}.`;
                    document.getElementById('sale-receipt-link').href = result.receipt_url;
                    document.getElementById('sale-complete-dialog').showModal();
                    document.getElementById('cash-received').value = '';
                    document.getElementById('payment-reference').value = '';
                    document.getElementById('discount-type').value = '';
                    document.getElementById('discount-id').value = '';
                    document.getElementById('discount-panel').hidden = true;
                    document.getElementById('open-discount').setAttribute('aria-expanded', 'false');
                    paymentMethod = 'cash';
                    document.querySelectorAll('.payment-option').forEach(option => option.classList.toggle('selected', option.dataset.method === 'cash'));
                    document.getElementById('cash-payment-fields').hidden = false;
                    document.getElementById('wallet-payment-fields').hidden = true;
                } catch (error) {
                    inform(error.message, true);
                } finally {
                    submitting = false;
                    updatePaymentState();
                }
            });
            document.getElementById('close-sale-dialog').addEventListener('click', () => {
                document.getElementById('sale-complete-dialog').close();
                notice.hidden = true;
            });

            refreshCart().catch(error => inform(error.message, true));
        });
    </script>

    <style>
        body.cashier-app-body { background:#09121c; color:#e7eef6; min-height:100vh; }
        .cashier-app-shell { max-width:none; min-height:100vh; padding:0 clamp(12px,2.4vw,36px) 28px; }
        .cashier-app-header { align-items:center; display:flex; height:58px; }
        .cashier-back-button { align-items:center; background:#1b2b3a; border:1px solid #2d4357; border-radius:8px; color:#e7eef6; display:inline-flex; gap:9px; padding:8px 13px; text-decoration:none; }
        .cashier-back-button:hover,.cashier-back-button:focus { background:#263b4e; border-color:#14b8a6; }
        .cashier-back-button span { color:#5eead4; font-size:1.2rem; }
        .cashier-app-main { background:transparent; border:0; border-radius:0; padding:0; }
        .cashier-app .button { font:inherit; }
        .cashier-app .pagination { color:#d5e0e9; margin-top:14px; }
        .cashier-app .pagination a { color:#d5e0e9; }
        .cashier-topbar { align-items:center; display:flex; justify-content:space-between; margin-bottom:18px; }
        .cashier-kicker { color:#74e4d3; font-size:.68rem; font-weight:800; letter-spacing:.14em; margin:0 0 3px; }
        .cashier-topbar h1 { color:#f8fafc; font-size:clamp(1.45rem,2.7vw,2rem); margin:0; }
        .cashier-shift-state { align-items:center; border:1px solid #334155; border-radius:999px; display:flex; font-size:.72rem; font-weight:800; gap:8px; padding:8px 12px; }
        .cashier-shift-state.is-open { background:#0f2c2d; border-color:#115e59; color:#99f6e4; }
        .cashier-shift-state.is-closed { background:#34251d; border-color:#854d0e; color:#fde68a; }
        .shift-dot { background:#34d399; border-radius:50%; height:8px; width:8px; }
        .is-closed .shift-dot { background:#f59e0b; }
        .shift-open-form { align-items:center; background:#172a38; border:1px solid #385366; border-radius:10px; display:flex; flex-wrap:wrap; gap:12px; margin-bottom:14px; padding:13px 16px; }
        .shift-open-form div { flex:1; min-width:190px; }
        .shift-open-form p { color:#aab8c5; font-size:.82rem; margin:0; }
        .shift-open-form label { font-size:.82rem; }
        .shift-open-form input,.payment-detail input,.discount-panel input,.discount-panel select { background:#101e2a; border:1px solid #3c5264; border-radius:7px; color:#f8fafc; font:inherit; min-width:0; padding:9px 11px; }
        .shift-open-banner { background:#0d292a; border:1px solid #145b55; border-radius:8px; color:#a7f3d0; font-size:.82rem; margin-bottom:14px; padding:9px 13px; }
        .cashier-primary-button,.cashier-secondary-button { align-items:center; background:#0f9f8f; border:0; border-radius:8px; color:#fff; cursor:pointer; display:inline-flex; font:inherit; font-weight:700; justify-content:center; padding:10px 15px; text-decoration:none; }
        .cashier-primary-button:hover,.cashier-primary-button:focus { background:#0d887c; }
        .cashier-primary-button:disabled { background:#425360; cursor:not-allowed; }
        .cashier-secondary-button { background:#263b4e; border:1px solid #42576b; }
        .cashier-notice { border-radius:8px; margin:0 0 12px; padding:10px 14px; }
        .cashier-notice[hidden] { display:none; }
        .cashier-notice.is-success { background:#0e302b; color:#a7f3d0; }
        .cashier-notice.is-error { background:#3a2024; color:#fecaca; }
        .cashier-workspace { align-items:start; display:grid; gap:18px; grid-template-columns:minmax(0,1.65fr) minmax(330px,.85fr); }
        .catalog-pane,.cart-pane { background:#121e2a; border:1px solid #243749; border-radius:12px; min-width:0; }
        .catalog-pane { padding:17px; }
        .catalog-toolbar { display:flex; gap:10px; }
        .catalog-search { display:flex; flex:1; gap:8px; min-width:0; }
        .catalog-search input { background:#0c1822; border:1px solid #2c4052; border-radius:8px; color:#f8fafc; flex:1; font:inherit; min-width:0; padding:11px 14px; }
        .catalog-search input::placeholder { color:#8394a5; }
        .store-qr-shortcut { align-items:center; background:#1c3041; border:1px solid #30485d; border-radius:8px; color:#a7f3d0; display:flex; font-size:1.4rem; justify-content:center; text-decoration:none; width:44px; }
        .category-tabs { display:flex; gap:7px; margin:14px 0; overflow-x:auto; padding-bottom:3px; }
        .category-tabs a { background:#1a2936; border:1px solid #2a3c4c; border-radius:999px; color:#c6d2de; flex:none; font-size:.78rem; padding:7px 12px; text-decoration:none; }
        .category-tabs a:hover,.category-tabs a.selected { background:#0e625b; border-color:#159e8e; color:#effdfa; }
        .product-grid { display:grid; gap:11px; grid-template-columns:repeat(3,minmax(0,1fr)); }
        .catalog-product { background:#f8fafc; border:1px solid #d5e0e8; border-radius:9px; color:#17212b; min-width:0; overflow:hidden; }
        .catalog-product-image { align-items:center; background:#e9f0f3; display:flex; height:112px; justify-content:center; overflow:hidden; }
        .catalog-product-image img { height:100%; object-fit:cover; width:100%; }
        .product-placeholder { align-items:center; background:#ccfbf1; border-radius:50%; color:#115e59; display:flex; font-size:2.1rem; font-weight:800; height:70px; justify-content:center; width:70px; }
        .catalog-product-details { padding:10px; }
        .catalog-category { color:#0f766e; font-size:.63rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
        .eligible-badge { background:#dcfce7; border-radius:999px; color:#166534; display:inline-block; font-size:.6rem; font-weight:700; margin-top:3px; padding:2px 6px; }
        .catalog-product h2 { color:#18232d; font-size:.9rem; line-height:1.3; margin:3px 0; min-height:2.3em; overflow-wrap:anywhere; }
        .catalog-code { color:#708090; font-size:.68rem; margin:0; overflow-wrap:anywhere; }
        .catalog-product-bottom { align-items:baseline; display:flex; flex-wrap:wrap; gap:3px 8px; justify-content:space-between; margin:8px 0; }
        .catalog-product-bottom strong { color:#0f766e; font-size:.95rem; }
        .catalog-product-bottom small { color:#697987; font-size:.67rem; }
        .add-product-button { background:#e2f5ef; border:1px solid #a7e4d1; border-radius:7px; color:#116454; cursor:pointer; font:inherit; font-size:.75rem; font-weight:800; padding:7px; width:100%; }
        .add-product-button:hover { background:#c9f0e2; }
        .add-product-button:disabled { cursor:wait; opacity:.65; }
        .catalog-empty { color:#cbd5e1; grid-column:1/-1; padding:24px; }
        .cart-pane { padding:17px; position:sticky; top:14px; }
        .cart-heading { align-items:center; display:flex; justify-content:space-between; }
        .cart-heading h2 { align-items:center; color:#f8fafc; display:flex; font-size:1.2rem; gap:9px; margin:0; }
        .cart-heading h2 span { align-items:center; background:#203547; border-radius:50%; color:#a7f3d0; display:inline-flex; font-size:.7rem; height:25px; justify-content:center; width:25px; }
        .text-button { background:transparent; border:0; color:#e7a5a5; cursor:pointer; font:inherit; font-size:.78rem; }
        .cart-empty { align-items:center; color:#91a1b0; display:flex; flex-direction:column; gap:4px; justify-content:center; min-height:140px; text-align:center; }
        .cart-empty[hidden] { display:none; }
        .cart-empty span { color:#506579; font-size:2.1rem; }
        .cart-empty strong { color:#c6d2de; }
        .cart-empty small { font-size:.75rem; }
        .cart-lines { max-height:34vh; overflow-y:auto; }
        .cart-line { align-items:flex-start; border-bottom:1px solid #253747; display:flex; gap:8px; justify-content:space-between; padding:12px 0; }
        .cart-line-main { min-width:0; }
        .cart-line-main>strong { color:#e7eef6; display:block; font-size:.82rem; overflow-wrap:anywhere; }
        .cart-line-main>small { color:#91a1b0; font-size:.7rem; }
        .cart-line-total { color:#e7eef6; flex:none; font-size:.82rem; }
        .promo-badge { background:#755719; border-radius:4px; color:#fde68a; font-size:.58rem; padding:2px 4px; }
        .cart-line-controls { align-items:center; display:flex; gap:8px; margin-top:7px; }
        .quantity-button { background:#1b3040; border:1px solid #345066; border-radius:5px; color:#d5e4ef; cursor:pointer; height:25px; width:26px; }
        .cart-line-controls>span { color:#f8fafc; font-size:.78rem; min-width:15px; text-align:center; }
        .remove-line { background:transparent; border:0; color:#e6a3a3; cursor:pointer; font-size:.7rem; margin-left:3px; }
        .cart-calculations { border-top:1px solid #2b3e4e; margin-top:10px; padding-top:9px; }
        .cart-calculations>div { align-items:center; color:#aab8c5; display:flex; font-size:.8rem; justify-content:space-between; padding:3px 0; }
        .cart-calculations strong { color:#e8eef4; font-variant-numeric:tabular-nums; }
        .cart-calculations .cart-grand-total { border-top:1px solid #34495a; color:#fff; font-size:1rem; margin-top:7px; padding-top:10px; }
        .cart-grand-total strong { color:#6ee7d0; font-size:1.35rem; }
        .discount-trigger { background:#1c2a38; border:1px solid #34485a; border-radius:8px; color:#cbd5e1; cursor:pointer; font:inherit; margin-top:13px; padding:9px 12px; text-align:left; width:100%; }
        .discount-trigger.is-active { border-color:#14b8a6; color:#99f6e4; }
        .discount-panel { background:#0d1923; border:1px solid #2c4254; border-radius:8px; display:grid; gap:6px; margin-top:8px; padding:11px; }
        .discount-panel[hidden] { display:none; }
        .discount-panel label,.payment-detail label { color:#cbd5e1; font-size:.72rem; font-weight:700; }
        .discount-panel select,.discount-panel input,.payment-detail input { width:100%; }
        .discount-panel small { color:#91a1b0; font-size:.68rem; margin-top:2px; }
        .payment-section { border-top:1px solid #293d4d; margin-top:14px; padding-top:13px; }
        .payment-section h3 { color:#e7eef6; font-size:.9rem; margin:0 0 9px; }
        .payment-options { display:grid; gap:6px; grid-template-columns:repeat(4,minmax(0,1fr)); }
        .payment-option { background:#1a2936; border:1px solid #304456; border-radius:7px; color:#afbdc9; cursor:pointer; font:inherit; font-size:.72rem; padding:8px 3px; }
        .payment-option.selected { background:#0d605a; border-color:#16a394; color:#fff; }
        .payment-detail { display:grid; gap:5px; margin-top:10px; }
        .payment-detail[hidden] { display:none; }
        .payment-detail p { color:#72e6c6; font-size:.75rem; margin:0; text-align:right; }
        .complete-sale-button { background:linear-gradient(100deg,#13a78f,#0e857b); border:0; border-radius:8px; color:#fff; cursor:pointer; font:inherit; font-size:.92rem; font-weight:800; margin-top:13px; padding:12px; width:100%; }
        .complete-sale-button:hover { filter:brightness(1.07); }
        .complete-sale-button:disabled { background:#415361; cursor:not-allowed; }
        .shift-required-note,.cart-footnote { color:#92a3b2; font-size:.7rem; margin:8px 0 0; text-align:center; }
        .sale-dialog { background:#14232f; border:1px solid #315164; border-radius:14px; color:#e7eef6; max-width:410px; padding:26px; text-align:center; width:calc(100% - 30px); }
        .sale-dialog::backdrop { background:rgba(2,8,15,.72); backdrop-filter:blur(3px); }
        .sale-dialog-icon { align-items:center; background:#0e5447; border:1px solid #20b89c; border-radius:50%; color:#9af3d4; display:flex; font-size:2rem; height:60px; justify-content:center; margin:0 auto 13px; width:60px; }
        .sale-dialog h2 { color:#f8fafc; margin:0; }
        .sale-dialog p { color:#b5c3cf; }
        .sale-dialog-actions { display:flex; gap:10px; justify-content:center; }
        .sr-only { height:1px; margin:-1px; overflow:hidden; position:absolute; width:1px; clip:rect(0,0,0,0); }
        @media(max-width:980px) { .cashier-workspace { grid-template-columns:minmax(0,1.25fr) minmax(300px,.9fr); } .product-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:740px) { .cashier-workspace { grid-template-columns:1fr; } .cart-pane { position:static; } .cart-lines { max-height:310px; } .product-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media(max-width:520px) { .cashier-app-shell { padding-left:10px; padding-right:10px; } .cashier-topbar { align-items:flex-start; flex-direction:column; gap:10px; } .shift-open-form { align-items:stretch; flex-direction:column; } .shift-open-form input { width:100%; } .product-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .catalog-pane,.cart-pane { padding:12px; } .catalog-product-image { height:94px; } .cashier-app-main { padding:0; } }
    </style>
@endsection
