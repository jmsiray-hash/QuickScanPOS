@extends('layouts.app')

@section('title', 'Cashier POS | QuickScanPos')

@section('content')
    <section class="cashier-app">
        <header class="cashier-topbar">
            <div>
                <p class="cashier-kicker">QUICKSCAN POS <span>·</span> REGISTER 01</p>
                <h1>Cashier dashboard</h1>
                <p class="cashier-subtitle">A clear view of your register, live cart and customer check-ins.</p>
            </div>
            <div class="cashier-topbar-actions">
                <time class="cashier-clock" id="cashier-clock"></time>
                <button type="button" class="report-quick-link" data-cashier-view="sales-report">
                    <span class="report-quick-icon" aria-hidden="true">₱</span>
                    <span>Today's sales <strong id="header-sales-total">₱0.00</strong></span>
                </button>
                <button type="button" class="report-quick-link" data-cashier-view="scan-report">
                    <span class="report-quick-icon" aria-hidden="true">⌁</span>
                    <span>Customer scans <strong id="header-scan-count">{{ $todayScanCount }}</strong></span>
                </button>
                <a class="report-quick-link" href="{{ route('customer-activity.index') }}">
                    <span class="report-quick-icon" aria-hidden="true">◉</span>
                    <span>Customer carts</span>
                </a>
                @if (auth()->user()->isAdmin())
                    <a class="report-quick-link" href="{{ route('settings.store.edit') }}">
                        <span class="report-quick-icon" aria-hidden="true">⚙</span>
                        <span>Settings</span>
                    </a>
                @endif
                <div class="cashier-shift-state {{ $activeShift ? 'is-open' : 'is-closed' }}">
                    <span class="shift-dot"></span>
                    {{ $activeShift ? 'SHIFT OPEN' : 'SHIFT NOT OPEN' }}
                </div>
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
                <div class="catalog-view-switch" role="tablist" aria-label="Cashier dashboard views">
                    <button class="catalog-view-tab is-active" type="button" role="tab" aria-selected="true" data-cashier-view="catalog">
                        <span aria-hidden="true">▦</span> Product catalog
                    </button>
                    <button class="catalog-view-tab" type="button" role="tab" aria-selected="false" data-cashier-view="scan-report">
                        <span aria-hidden="true">⌁</span> Customer QR report
                        <span class="report-count" id="report-scan-count">{{ $todayScanCount }}</span>
                    </button>
                    <button class="catalog-view-tab" type="button" role="tab" aria-selected="false" data-cashier-view="sales-report">
                        <span aria-hidden="true">₱</span> Sales report
                    </button>
                </div>
                <div id="catalog-view" class="catalog-view">
                <div class="catalog-toolbar">
                    <form method="GET" action="{{ route('cashier-dashboard') }}" class="catalog-search">
                        @if ($category !== '')
                            <input type="hidden" name="category" value="{{ $category }}">
                        @endif
                        <label class="sr-only" for="catalog-search">Search products</label>
                        <input id="catalog-search" name="search" value="{{ $search }}" placeholder="Search product name or barcode..." autocomplete="off">
                        <button class="cashier-primary-button" type="submit">Search</button>
                    </form>
                    <button id="voice-search" class="voice-search-button" type="button" hidden>Speak</button>
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
                </div>
                <section id="scan-report-view" class="scan-report-view" role="tabpanel" hidden>
                    <div class="scan-report-heading">
                        <div>
                            <p class="cashier-kicker">REGISTER ACTIVITY</p>
                            <h2>Customer QR scans</h2>
                            <p>Every customer checkout QR accepted by this register.</p>
                        </div>
                        <span class="report-live-indicator"><i></i> Live report</span>
                    </div>
                    <div class="scan-report-summary">
                        <div><span>Scans today</span><strong id="report-today-total">{{ $todayScanCount }}</strong></div>
                        <div><span>Showing latest</span><strong>20 <small>events</small></strong></div>
                        <p id="report-refresh-status">Updated just now</p>
                    </div>
                    <div id="scan-report-list" class="scan-report-list" aria-live="polite"></div>
                </section>
                <section id="sales-report-view" class="sales-report-view" role="tabpanel" hidden>
                    <div class="sales-report-heading">
                        <div>
                            <p class="sales-report-kicker">REGISTER PERFORMANCE</p>
                            <h2>Today's sales</h2>
                            <p>Completed sales attributed to your cashier account.</p>
                        </div>
                        <a class="sales-full-report" href="{{ route('sales.index') }}">Full sales reports <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="sales-report-controls">
                        <label for="sales-report-date">Business date</label>
                        <input id="sales-report-date" type="date" value="{{ today()->toDateString() }}">
                        <button id="refresh-sales-report" class="sales-refresh-button" type="button">Refresh</button>
                        <span id="sales-report-state" role="status">Ready</span>
                    </div>
                    <div class="sales-metric-grid">
                        <article class="sales-metric sales-metric-primary"><span>Net completed sales</span><strong id="sales-total">₱0.00</strong></article>
                        <article class="sales-metric"><span>Transactions</span><strong id="sales-count">0</strong></article>
                        <article class="sales-metric"><span>Cash</span><strong id="sales-cash">₱0.00</strong></article>
                        <article class="sales-metric"><span>Digital / card</span><strong id="sales-digital">₱0.00</strong></article>
                    </div>
                    <div class="sales-transaction-heading"><h3>Recent transactions</h3><span>Latest 30 for selected date</span></div>
                    <div id="sales-transaction-list" class="sales-transaction-list" aria-live="polite"></div>
                </section>
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
        <nav class="cashier-action-tray" aria-label="Cashier quick actions">
            <button type="button" class="tray-action tray-action-primary" id="start-order">
                <span aria-hidden="true">＋</span><span>Start order</span>
            </button>
            <button type="button" class="tray-action is-selected" data-cashier-view="catalog">
                <span aria-hidden="true">▦</span><span>Menu</span>
            </button>
            <button type="button" class="tray-action" data-cashier-view="sales-report">
                <span aria-hidden="true">₱</span><span>Today's sales</span>
            </button>
            <button type="button" class="tray-action" data-cashier-view="scan-report">
                <span aria-hidden="true">⌁</span><span>QR activity</span>
            </button>
            <a class="tray-action" href="{{ route('customer-activity.index') }}">
                <span aria-hidden="true">◉</span><span>Customer carts</span>
            </a>
        </nav>

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
            const scanReportUrl = @json(route('cashier-dashboard.scan-report'));
            const salesReportUrl = @json(route('cashier-dashboard.sales-report'));
            let scanReports = @json($scanReports);
            let cart = null;
            let orderStarted = false;
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
            function renderScanReports(reports, todayCount) {
                scanReports = reports;
                document.getElementById('header-scan-count').textContent = String(todayCount);
                document.getElementById('report-scan-count').textContent = String(todayCount);
                document.getElementById('report-today-total').textContent = String(todayCount);
                const list = document.getElementById('scan-report-list');
                if (!reports.length) {
                    list.innerHTML = '<div class="scan-report-empty"><span aria-hidden="true">⌁</span><strong>No customer scans yet</strong><p>Accepted customer checkout QR codes will appear here.</p></div>';
                    return;
                }
                list.innerHTML = reports.map(scan => `
                    <article class="scan-report-row">
                        <div class="scan-report-avatar" aria-hidden="true">${escapeHtml((scan.customer || '?').trim().charAt(0).toUpperCase())}</div>
                        <div class="scan-report-customer">
                            <strong>${escapeHtml(scan.customer)}</strong>
                            <span>${escapeHtml(scan.token)} · ${Number(scan.item_count)} ${Number(scan.item_count) === 1 ? 'item' : 'items'}</span>
                        </div>
                        <div class="scan-report-meta">
                            <span class="scan-status ${scan.status === 'PAID' ? 'is-paid' : ''}">${escapeHtml(scan.status)}</span>
                            <time>${escapeHtml(scan.scanned_at)}</time>
                        </div>
                        <div class="scan-report-total">${scan.total === null ? '—' : money(scan.total)}</div>
                        ${scan.activity_url
                            ? `<a class="scan-report-open" href="${escapeHtml(scan.activity_url)}" aria-label="View ${escapeHtml(scan.customer)} customer activity">↗</a>`
                            : '<span class="scan-report-open-placeholder"></span>'}
                    </article>
                `).join('');
            }
            async function refreshScanReport() {
                const result = await request(scanReportUrl);
                renderScanReports(result.scans, result.today_count);
                document.getElementById('report-refresh-status').textContent = 'Updated just now';
            }
            function renderSalesReport(report) {
                document.getElementById('header-sales-total').textContent = money(report.revenue);
                document.getElementById('sales-total').textContent = money(report.revenue);
                document.getElementById('sales-count').textContent = String(report.transaction_count);
                document.getElementById('sales-cash').textContent = money(report.cash_sales);
                document.getElementById('sales-digital').textContent = money(report.digital_sales);
                const list = document.getElementById('sales-transaction-list');
                if (!report.transactions.length) {
                    list.innerHTML = '<div class="sales-report-empty"><span aria-hidden="true">₱</span><strong>No transactions for this date</strong><p>Completed sales from your register will appear here.</p></div>';
                    return;
                }
                list.innerHTML = report.transactions.map(sale => `
                    <article class="sales-transaction-row ${sale.status === 'VOIDED' ? 'is-voided' : ''}">
                        <span class="sales-transaction-id">#${Number(sale.id)}</span>
                        <div class="sales-transaction-customer">
                            <strong>${escapeHtml(sale.customer || 'Walk-in customer')}</strong>
                            <span>${Number(sale.item_count)} ${Number(sale.item_count) === 1 ? 'item' : 'items'} · ${escapeHtml(sale.created_at)}</span>
                        </div>
                        <span class="sales-method">${escapeHtml(sale.payment_method)}</span>
                        <span class="sales-transaction-status">${escapeHtml(sale.status)}</span>
                        <strong class="sales-transaction-total">${money(sale.total)}</strong>
                        <a class="sales-receipt-link" href="${escapeHtml(sale.receipt_url)}" aria-label="View receipt for sale ${Number(sale.id)}">↗</a>
                    </article>
                `).join('');
            }
            async function refreshSalesReport() {
                const state = document.getElementById('sales-report-state');
                state.textContent = 'Refreshing…';
                try {
                    const date = document.getElementById('sales-report-date').value;
                    const result = await request(salesReportUrl + '?date=' + encodeURIComponent(date));
                    renderSalesReport(result);
                    state.textContent = 'Updated just now';
                } catch (error) {
                    state.textContent = error.message || 'Unable to refresh sales report';
                    throw error;
                }
            }
            function showCashierView(viewName) {
                const showReport = viewName === 'scan-report';
                const showSales = viewName === 'sales-report';
                document.getElementById('catalog-view').hidden = showReport || showSales;
                document.getElementById('scan-report-view').hidden = !showReport;
                document.getElementById('sales-report-view').hidden = !showSales;
                document.querySelectorAll('.catalog-view-tab').forEach(tab => {
                    const selected = tab.dataset.cashierView === viewName;
                    tab.classList.toggle('is-active', selected);
                    tab.setAttribute('aria-selected', String(selected));
                });
                document.querySelectorAll('.tray-action[data-cashier-view]').forEach(button => {
                    button.classList.toggle('is-selected', button.dataset.cashierView === viewName);
                });
                if (showReport) {
                    refreshScanReport().catch(() => {
                        document.getElementById('report-refresh-status').textContent = 'Unable to refresh the scan report';
                    });
                }
                if (showSales) refreshSalesReport().catch(() => {});
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
                if (result.cart.items?.length) orderStarted = true;
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
                    if (!orderStarted) {
                        inform('Start an order before adding products. Your cart will stay ready here.', true);
                        document.getElementById('start-order').focus();
                        return;
                    }
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
                orderStarted = false;
                inform('Cart cleared.');
            });
            document.getElementById('start-order').addEventListener('click', () => {
                orderStarted = true;
                showCashierView('catalog');
                document.getElementById('catalog-search').focus();
                inform(cart?.items?.length ? 'Order ready. Continue editing the current cart.' : 'New order started. Search or choose a product to add.');
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
                    orderStarted = false;
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

            document.querySelectorAll('[data-cashier-view]').forEach(button => {
                button.addEventListener('click', () => showCashierView(button.dataset.cashierView));
            });
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const voiceButton = document.getElementById('voice-search');
            if (SpeechRecognition) {
                voiceButton.hidden = false;
                voiceButton.addEventListener('click', () => {
                    const recognition = new SpeechRecognition();
                    recognition.lang = document.documentElement.lang || 'en';
                    recognition.interimResults = false;
                    recognition.maxAlternatives = 1;
                    voiceButton.disabled = true;
                    voiceButton.textContent = 'Listening…';
                    inform('Listening for a product or category.');
                    recognition.onresult = event => {
                        const spoken = String(event.results[0][0].transcript || '').trim();
                        if (!spoken) {
                            inform('No search phrase was recognized. Try again.', true);
                            return;
                        }
                        const matchingCategory = Array.from(document.querySelectorAll('.category-tabs a'))
                            .find(link => link.textContent.trim().toLowerCase() === spoken.toLowerCase());
                        if (matchingCategory) {
                            window.location.assign(matchingCategory.href);
                            return;
                        }
                        document.getElementById('catalog-search').value = spoken;
                        document.querySelector('.catalog-search').requestSubmit();
                    };
                    recognition.onerror = event => inform('Voice search failed: ' + event.error + '. You can type in the product search.', true);
                    recognition.onend = () => {
                        voiceButton.disabled = false;
                        voiceButton.textContent = 'Speak';
                    };
                    try {
                        recognition.start();
                    } catch (error) {
                        voiceButton.disabled = false;
                        voiceButton.textContent = 'Speak';
                        inform(error.message || 'Voice search could not start. You can type in the product search.', true);
                    }
                });
            }
            function updateClock() {
                const now = new Date();
                document.getElementById('cashier-clock').textContent = new Intl.DateTimeFormat(undefined, {
                    weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
                }).format(now);
            }
            updateClock();
            window.setInterval(updateClock, 30000);
            document.getElementById('refresh-sales-report').addEventListener('click', () => refreshSalesReport().catch(() => {}));
            document.getElementById('sales-report-date').addEventListener('change', () => refreshSalesReport().catch(() => {}));
            renderScanReports(scanReports, {{ $todayScanCount }});
            refreshSalesReport().catch(() => {});
            window.setInterval(() => {
                if (document.visibilityState === 'visible') {
                    refreshScanReport().catch(() => {
                        document.getElementById('report-refresh-status').textContent = 'Unable to refresh the scan report';
                    });
                    refreshSalesReport().catch(() => {});
                }
            }, 15000);
            refreshCart().catch(error => inform(error.message, true));
        });
    </script>

    <style>
        body.cashier-app-body { background:radial-gradient(ellipse at 18% -10%,rgba(13,148,136,.18),transparent 42%),#08121c; color:#e7eef6; height:100vh; min-height:0; overflow:hidden; }
        @supports (height:100dvh) { body.cashier-app-body { height:100dvh; } }
        .cashier-app-shell { height:100vh; max-width:none; overflow:hidden; padding:0 22px 14px; }
        @supports (height:100dvh) { .cashier-app-shell { height:100dvh; } }
        .cashier-app-header { align-items:center; display:flex; height:48px; }
        .cashier-back-button { align-items:center; background:linear-gradient(135deg,#182b39,#11212d); border:1px solid #294253; border-radius:9px; color:#e7eef6; display:inline-flex; font-size:.8rem; gap:7px; padding:6px 11px; text-decoration:none; }
        .cashier-back-button:hover,.cashier-back-button:focus { background:#263b4e; border-color:#14b8a6; }
        .cashier-back-button span { color:#5eead4; font-size:1.2rem; }
        .cashier-app-main { background:transparent; border:0; border-radius:0; display:flex; flex-direction:column; height:calc(100vh - 60px); min-height:0; overflow:hidden; padding:0; }
        @supports (height:100dvh) { .cashier-app-main { height:calc(100dvh - 60px); } }
        .cashier-app-main > .alert { flex:none; margin:0 0 6px; padding:6px 10px; }
        .cashier-app .button { font:inherit; }
        .cashier-app .pagination { color:#d5e0e9; margin-top:14px; }
        .cashier-app .pagination a { color:#d5e0e9; }
        .cashier-app { display:flex; flex:1; flex-direction:column; min-height:0; }
        .cashier-topbar { align-items:center; display:flex; flex:none; justify-content:space-between; margin-bottom:10px; min-height:54px; }
        .cashier-kicker { color:#74e4d3; font-size:.58rem; font-weight:800; letter-spacing:.13em; margin:0 0 2px; }
        .cashier-topbar h1 { color:#f8fafc; font-size:1.48rem; letter-spacing:-.035em; margin:0; }
        .cashier-subtitle { color:#8ea1b1; font-size:.7rem; margin:1px 0 0; }
        .cashier-topbar-actions { align-items:center; display:flex; gap:10px; }
        .cashier-clock { color:#8ea1b1; font-size:.65rem; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .report-quick-link { align-items:center; background:linear-gradient(135deg,#132935,#10212c); border:1px solid #294253; border-radius:9px; color:#c7d7e2; cursor:pointer; display:flex; font:inherit; font-size:.7rem; gap:8px; padding:7px 10px; text-decoration:none; transition:border-color .18s,transform .18s; }
        .report-quick-link:hover { border-color:#27b9a7; transform:translateY(-1px); }
        .report-quick-link strong { background:#0d645b; border-radius:999px; color:#d5fff3; margin-left:3px; min-width:20px; padding:2px 6px; }
        .report-quick-icon { color:#5eead4; font-size:1.1rem; }
        .cashier-shift-state { align-items:center; border:1px solid #334155; border-radius:999px; display:flex; font-size:.63rem; font-weight:800; gap:7px; padding:7px 11px; }
        .cashier-shift-state.is-open { background:#0f2c2d; border-color:#115e59; color:#99f6e4; }
        .cashier-shift-state.is-closed { background:#34251d; border-color:#854d0e; color:#fde68a; }
        .shift-dot { background:#34d399; border-radius:50%; height:8px; width:8px; }
        .is-closed .shift-dot { background:#f59e0b; }
        .shift-open-form { align-items:center; background:#172a38; border:1px solid #385366; border-radius:8px; display:flex; flex:none; flex-wrap:wrap; gap:8px; margin-bottom:8px; padding:7px 10px; }
        .shift-open-form div { flex:1; min-width:160px; }
        .shift-open-form p { color:#aab8c5; font-size:.7rem; margin:0; }
        .shift-open-form strong,.shift-open-form label { font-size:.75rem; }
        .shift-open-form input,.payment-detail input,.discount-panel input,.discount-panel select { background:#101e2a; border:1px solid #3c5264; border-radius:6px; color:#f8fafc; font:inherit; min-width:0; padding:6px 8px; }
        .shift-open-banner { background:#0d292a; border:1px solid #145b55; border-radius:7px; color:#a7f3d0; flex:none; font-size:.72rem; margin-bottom:8px; padding:6px 10px; }
        .cashier-primary-button,.cashier-secondary-button { align-items:center; background:#0f9f8f; border:0; border-radius:7px; color:#fff; cursor:pointer; display:inline-flex; font:inherit; font-size:.8rem; font-weight:700; justify-content:center; padding:7px 11px; text-decoration:none; }
        .cashier-primary-button:hover,.cashier-primary-button:focus { background:#0d887c; }
        .cashier-primary-button:disabled { background:#425360; cursor:not-allowed; }
        .cashier-secondary-button { background:#263b4e; border:1px solid #42576b; }
        .cashier-notice { border-radius:7px; flex:none; margin:0 0 7px; padding:6px 10px; }
        .cashier-notice[hidden] { display:none; }
        .cashier-notice.is-success { background:#0e302b; color:#a7f3d0; }
        .cashier-notice.is-error { background:#3a2024; color:#fecaca; }
        .cashier-workspace { align-items:stretch; display:grid; flex:1; gap:14px; grid-template-columns:minmax(0,1.65fr) minmax(320px,.85fr); min-height:0; }
        .catalog-pane,.cart-pane { background:linear-gradient(145deg,rgba(20,34,47,.97),rgba(14,26,37,.98)); border:1px solid #294051; border-radius:13px; box-shadow:0 18px 42px rgba(0,0,0,.2),inset 0 1px rgba(255,255,255,.025); min-height:0; min-width:0; }
        .catalog-pane { display:flex; flex-direction:column; overflow:hidden; padding:14px; }
        .catalog-view { display:flex; flex:1; flex-direction:column; min-height:0; }
        .catalog-view[hidden],.scan-report-view[hidden],.sales-report-view[hidden] { display:none; }
        .catalog-view-switch { align-items:center; border-bottom:1px solid #253a49; display:flex; flex:none; gap:5px; margin:-2px 0 10px; padding-bottom:9px; }
        .catalog-view-tab { align-items:center; background:transparent; border:1px solid transparent; border-radius:7px; color:#9eafbc; cursor:pointer; display:inline-flex; font:inherit; font-size:.7rem; gap:7px; padding:6px 9px; transition:background .18s,color .18s,border-color .18s; }
        .catalog-view-tab:hover { background:#1a2c39; color:#eaf4f5; }
        .catalog-view-tab.is-active { background:#0e3938; border-color:#176d65; color:#baffed; }
        .report-count { background:#263c49; border-radius:999px; color:#bfeee3; font-size:.6rem; min-width:20px; padding:1px 6px; text-align:center; }
        .catalog-toolbar { display:flex; gap:10px; }
        .catalog-search { display:flex; flex:1; gap:8px; min-width:0; }
        .catalog-search input { background:#0c1822; border:1px solid #2c4052; border-radius:7px; color:#f8fafc; flex:1; font:inherit; min-width:0; padding:8px 11px; }
        .catalog-search input::placeholder { color:#8394a5; }
        .voice-search-button { background:#17343b; border:1px solid #2e6b68; border-radius:7px; color:#b4f1de; cursor:pointer; font:inherit; font-size:.68rem; padding:6px 9px; }
        .voice-search-button:disabled { cursor:wait; opacity:.65; }
        .store-qr-shortcut { align-items:center; background:#1c3041; border:1px solid #30485d; border-radius:7px; color:#a7f3d0; display:flex; font-size:1.2rem; justify-content:center; text-decoration:none; width:38px; }
        .category-tabs { display:flex; flex:none; gap:6px; margin:9px 0; overflow-x:auto; padding-bottom:2px; }
        .category-tabs a { background:#1a2936; border:1px solid #2a3c4c; border-radius:999px; color:#c6d2de; flex:none; font-size:.7rem; padding:5px 9px; text-decoration:none; }
        .category-tabs a:hover,.category-tabs a.selected { background:#0e625b; border-color:#159e8e; color:#effdfa; }
        .product-grid { align-content:start; display:grid; flex:1; gap:8px; grid-auto-rows:max-content; grid-template-columns:repeat(4,minmax(0,1fr)); min-height:0; overflow-y:auto; padding:1px 5px 8px 1px; }
        .catalog-product { background:linear-gradient(155deg,#fff,#f3f8f8); border:1px solid #d5e0e8; border-radius:9px; box-shadow:0 5px 14px rgba(2,12,22,.12); color:#17212b; min-width:0; overflow:hidden; transition:transform .18s,box-shadow .18s; }
        .catalog-product:hover { box-shadow:0 11px 22px rgba(2,12,22,.22); transform:translateY(-2px); }
        .catalog-product-image { align-items:center; background:#e9f0f3; display:flex; height:clamp(58px,10vh,92px); justify-content:center; overflow:hidden; }
        .catalog-product-image img { height:100%; object-fit:cover; width:100%; }
        .product-placeholder { align-items:center; background:#ccfbf1; border-radius:50%; color:#115e59; display:flex; font-size:1.6rem; font-weight:800; height:54px; justify-content:center; width:54px; }
        .catalog-product-details { padding:7px; }
        .catalog-category { color:#0f766e; font-size:.56rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        .eligible-badge { background:#dcfce7; border-radius:999px; color:#166534; display:inline-block; font-size:.52rem; font-weight:700; margin-top:2px; padding:1px 5px; }
        .catalog-product h2 { color:#18232d; font-size:.78rem; line-height:1.2; margin:2px 0; min-height:1.9em; overflow-wrap:anywhere; }
        .catalog-code { color:#708090; font-size:.6rem; margin:0; overflow-wrap:anywhere; }
        .catalog-product-bottom { align-items:baseline; display:flex; flex-wrap:wrap; gap:2px 5px; justify-content:space-between; margin:5px 0; }
        .catalog-product-bottom strong { color:#0f766e; font-size:.82rem; }
        .catalog-product-bottom small { color:#697987; font-size:.59rem; }
        .add-product-button { background:#e2f5ef; border:1px solid #a7e4d1; border-radius:6px; color:#116454; cursor:pointer; font:inherit; font-size:.67rem; font-weight:800; padding:5px; width:100%; }
        .add-product-button:hover { background:#c9f0e2; }
        .add-product-button:disabled { cursor:wait; opacity:.65; }
        .catalog-empty { color:#cbd5e1; grid-column:1/-1; padding:24px; }
        .cart-pane { display:flex; flex-direction:column; overflow:hidden; padding:14px; }
        .cart-heading { align-items:center; display:flex; justify-content:space-between; }
        .cart-heading h2 { align-items:center; color:#f8fafc; display:flex; font-size:1.05rem; gap:8px; margin:0; }
        .cart-heading h2 span { align-items:center; background:#203547; border-radius:50%; color:#a7f3d0; display:inline-flex; font-size:.65rem; height:22px; justify-content:center; width:22px; }
        .text-button { background:transparent; border:0; color:#e6a3a3; cursor:pointer; font:inherit; font-size:.72rem; }
        .cart-empty { align-items:center; color:#91a1b0; display:flex; flex:1; flex-direction:column; gap:4px; justify-content:center; min-height:65px; text-align:center; }
        .cart-empty[hidden] { display:none; }
        .cart-empty span { color:#506579; font-size:1.7rem; }
        .cart-empty strong { color:#c6d2de; }
        .cart-empty small { font-size:.75rem; }
        .cart-lines { flex:1; min-height:40px; overflow-y:auto; }
        .cart-line { align-items:flex-start; border-bottom:1px solid #253747; display:flex; gap:8px; justify-content:space-between; padding:7px 0; }
        .cart-line-main { min-width:0; }
        .cart-line-main>strong { color:#e7eef6; display:block; font-size:.75rem; overflow-wrap:anywhere; }
        .cart-line-main>small { color:#91a1b0; font-size:.64rem; }
        .cart-line-total { color:#e7eef6; flex:none; font-size:.75rem; }
        .promo-badge { background:#755719; border-radius:4px; color:#fde68a; font-size:.58rem; padding:2px 4px; }
        .cart-line-controls { align-items:center; display:flex; gap:6px; margin-top:5px; }
        .quantity-button { background:#1b3040; border:1px solid #345066; border-radius:4px; color:#d5e4ef; cursor:pointer; height:22px; width:24px; }
        .cart-line-controls>span { color:#f8fafc; font-size:.7rem; min-width:15px; text-align:center; }
        .remove-line { background:transparent; border:0; color:#e6a3a3; cursor:pointer; font-size:.63rem; margin-left:3px; }
        .cart-calculations { border-top:1px solid #2b3e4e; flex:none; margin-top:6px; padding-top:5px; }
        .cart-calculations>div { align-items:center; color:#aab8c5; display:flex; font-size:.72rem; justify-content:space-between; padding:2px 0; }
        .cart-calculations strong { color:#e8eef4; font-variant-numeric:tabular-nums; }
        .cart-calculations .cart-grand-total { border-top:1px solid #34495a; color:#fff; font-size:.9rem; margin-top:5px; padding-top:6px; }
        .cart-grand-total strong { color:#6ee7d0; font-size:1.12rem; }
        .discount-trigger { background:#1c2a38; border:1px solid #34485a; border-radius:7px; color:#cbd5e1; cursor:pointer; flex:none; font:inherit; font-size:.75rem; margin-top:7px; padding:7px 10px; text-align:left; width:100%; }
        .discount-trigger.is-active { border-color:#14b8a6; color:#99f6e4; }
        .discount-panel { background:#0d1923; border:1px solid #2c4254; border-radius:7px; display:grid; gap:5px; flex:none; margin-top:6px; padding:8px; }
        .discount-panel[hidden] { display:none; }
        .discount-panel label,.payment-detail label { color:#cbd5e1; font-size:.72rem; font-weight:700; }
        .discount-panel select,.discount-panel input,.payment-detail input { width:100%; }
        .discount-panel small { color:#91a1b0; font-size:.68rem; margin-top:2px; }
        .payment-section { border-top:1px solid #293d4d; flex:none; margin-top:8px; padding-top:8px; }
        .payment-section h3 { color:#e7eef6; font-size:.82rem; margin:0 0 6px; }
        .payment-options { display:grid; gap:6px; grid-template-columns:repeat(4,minmax(0,1fr)); }
        .payment-option { background:#1a2936; border:1px solid #304456; border-radius:6px; color:#afbdc9; cursor:pointer; font:inherit; font-size:.66rem; padding:6px 3px; }
        .payment-option.selected { background:#0d605a; border-color:#16a394; color:#fff; }
        .payment-detail { display:grid; gap:4px; margin-top:7px; }
        .payment-detail[hidden] { display:none; }
        .payment-detail p { color:#72e6c6; font-size:.75rem; margin:0; text-align:right; }
        .complete-sale-button { background:linear-gradient(105deg,#18b69e,#0d8279); border:1px solid rgba(123,255,222,.18); border-radius:8px; box-shadow:0 7px 18px rgba(7,128,112,.18); color:#fff; cursor:pointer; font:inherit; font-size:.82rem; font-weight:800; margin-top:8px; padding:10px; width:100%; }
        .complete-sale-button:hover { filter:brightness(1.07); }
        .complete-sale-button:disabled { background:#415361; cursor:not-allowed; }
        .shift-required-note,.cart-footnote { color:#92a3b2; flex:none; font-size:.62rem; margin:5px 0 0; text-align:center; }
        .sale-dialog { background:#14232f; border:1px solid #315164; border-radius:14px; color:#e7eef6; max-width:410px; padding:26px; text-align:center; width:calc(100% - 30px); }
        .sale-dialog::backdrop { background:rgba(2,8,15,.72); backdrop-filter:blur(3px); }
        .sale-dialog-icon { align-items:center; background:#0e5447; border:1px solid #20b89c; border-radius:50%; color:#9af3d4; display:flex; font-size:2rem; height:60px; justify-content:center; margin:0 auto 13px; width:60px; }
        .sale-dialog h2 { color:#f8fafc; margin:0; }
        .sale-dialog p { color:#b5c3cf; }
        .sale-dialog-actions { display:flex; gap:10px; justify-content:center; }
        .cashier-action-tray { align-items:center; background:linear-gradient(100deg,#10212c,#132936); border:1px solid #2b4351; border-radius:10px; box-shadow:0 10px 24px rgba(0,0,0,.2); display:flex; flex:none; gap:6px; justify-content:center; margin-top:9px; padding:5px; }
        .tray-action { align-items:center; background:transparent; border:1px solid transparent; border-radius:7px; color:#aebdc8; cursor:pointer; display:flex; font:inherit; font-size:.65rem; gap:6px; justify-content:center; min-width:105px; padding:6px 10px; text-decoration:none; }
        .tray-action>span:first-child { color:#70deca; font-size:.95rem; }
        .tray-action:hover,.tray-action.is-selected { background:#1b3441; border-color:#335161; color:#f0f8fa; }
        .tray-action-primary { background:linear-gradient(110deg,#16a991,#0e8178); color:#fff; font-weight:800; }
        .tray-action-primary>span:first-child { color:#d5fff3; }
        .tray-action-primary:hover { background:linear-gradient(110deg,#1bbda1,#11958b); border-color:#4ac7ae; }
        .scan-report-view { display:flex; flex:1; flex-direction:column; min-height:0; overflow:hidden; }
        .scan-report-heading { align-items:center; display:flex; flex:none; justify-content:space-between; padding:5px 4px 10px; }
        .scan-report-heading h2 { color:#f4fafb; font-size:1.15rem; letter-spacing:-.02em; margin:0; }
        .scan-report-heading p:not(.cashier-kicker) { color:#8ea1b1; font-size:.68rem; margin:2px 0 0; }
        .report-live-indicator { align-items:center; background:#0d302d; border:1px solid #1a5d53; border-radius:999px; color:#9cebd5; display:inline-flex; font-size:.62rem; gap:6px; padding:5px 9px; }
        .report-live-indicator i { background:#43d6a5; border-radius:50%; box-shadow:0 0 10px rgba(67,214,165,.65); height:6px; width:6px; }
        .scan-report-summary { align-items:center; background:linear-gradient(105deg,#132a35,#11232e); border:1px solid #294352; border-radius:9px; display:flex; flex:none; gap:28px; margin:0 0 9px; padding:8px 11px; }
        .scan-report-summary div { display:grid; gap:1px; }
        .scan-report-summary span { color:#8da1af; font-size:.59rem; }
        .scan-report-summary strong { color:#e9fbf5; font-size:.95rem; line-height:1.1; }
        .scan-report-summary strong small { color:#91a5b2; font-size:.59rem; font-weight:500; }
        .scan-report-summary>p { color:#7f929f; font-size:.6rem; margin:0 0 0 auto; }
        .scan-report-list { flex:1; min-height:0; overflow-y:auto; }
        .scan-report-row { align-items:center; border-bottom:1px solid #253a49; display:grid; gap:10px; grid-template-columns:34px minmax(0,1fr) auto 84px 27px; min-height:56px; padding:7px 5px; }
        .scan-report-row:hover { background:rgba(38,67,78,.28); }
        .scan-report-avatar { align-items:center; background:linear-gradient(145deg,#175b55,#173a46); border:1px solid #2d746c; border-radius:50%; color:#bff9e7; display:flex; font-size:.78rem; font-weight:800; height:31px; justify-content:center; width:31px; }
        .scan-report-customer { display:grid; min-width:0; }
        .scan-report-customer strong { color:#e7f0f4; font-size:.72rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .scan-report-customer span,.scan-report-meta time { color:#8397a5; font-size:.6rem; }
        .scan-report-meta { align-items:flex-end; display:flex; flex-direction:column; gap:3px; }
        .scan-status { background:#203442; border:1px solid #344d5c; border-radius:999px; color:#b2c2cc; font-size:.53rem; font-weight:800; letter-spacing:.03em; padding:2px 6px; }
        .scan-status.is-paid { background:#0d342e; border-color:#1b6658; color:#8ce5c8; }
        .scan-report-total { color:#d9f7ed; font-size:.7rem; font-variant-numeric:tabular-nums; text-align:right; }
        .scan-report-open { align-items:center; border:1px solid #345162; border-radius:6px; color:#92d8c8; display:flex; font-size:.8rem; height:25px; justify-content:center; text-decoration:none; width:25px; }
        .scan-report-open:hover { background:#14574f; border-color:#24a28f; }
        .scan-report-open-placeholder { width:25px; }
        .scan-report-empty { align-items:center; color:#8498a7; display:flex; flex:1; flex-direction:column; justify-content:center; min-height:190px; text-align:center; }
        .scan-report-empty>span { color:#4e8d82; font-size:2rem; }
        .scan-report-empty strong { color:#d3e2e8; font-size:.85rem; }
        .scan-report-empty p { font-size:.68rem; margin:3px 0; }
        .sales-report-view { background:linear-gradient(145deg,#fff,#f3f8f8); border:1px solid #d5e5e3; border-radius:11px; box-shadow:0 12px 30px rgba(2,12,22,.14); color:#17212b; display:flex; flex:1; flex-direction:column; min-height:0; overflow:hidden; padding:14px; }
        .sales-report-heading { align-items:center; display:flex; flex:none; justify-content:space-between; padding:1px 2px 9px; }
        .sales-report-kicker { color:#0f766e; font-size:.56rem; font-weight:800; letter-spacing:.13em; margin:0 0 2px; }
        .sales-report-heading h2 { color:#142a34; font-size:1.13rem; letter-spacing:-.02em; margin:0; }
        .sales-report-heading p:not(.sales-report-kicker) { color:#71818a; font-size:.66rem; margin:2px 0 0; }
        .sales-full-report { background:#e9f7f3; border:1px solid #b8e3d8; border-radius:7px; color:#11685d; font-size:.65rem; font-weight:700; padding:6px 9px; text-decoration:none; }
        .sales-report-controls { align-items:center; background:#f0f7f7; border:1px solid #d9e8e7; border-radius:8px; display:flex; flex:none; gap:7px; margin-bottom:9px; padding:6px 8px; }
        .sales-report-controls label { color:#50636c; font-size:.63rem; font-weight:700; }
        .sales-report-controls input { background:white; border:1px solid #cbd9d8; border-radius:5px; color:#24353d; font:inherit; font-size:.67rem; padding:5px 7px; }
        .sales-refresh-button { background:#e3f5ef; border:1px solid #b2dfd1; border-radius:5px; color:#126657; cursor:pointer; font:inherit; font-size:.64rem; font-weight:700; padding:5px 8px; }
        .sales-report-controls>span { color:#71838b; font-size:.6rem; margin-left:auto; }
        .sales-metric-grid { display:grid; flex:none; gap:7px; grid-template-columns:1.25fr repeat(3,minmax(0,1fr)); margin-bottom:10px; }
        .sales-metric { background:#fff; border:1px solid #dce8e7; border-radius:8px; display:grid; gap:3px; padding:8px 10px; }
        .sales-metric-primary { background:linear-gradient(125deg,#e7faf4,#f4fbfa); border-color:#afe1d4; }
        .sales-metric span { color:#667b82; font-size:.58rem; }
        .sales-metric strong { color:#263942; font-size:1rem; font-variant-numeric:tabular-nums; }
        .sales-metric-primary strong { color:#087c6c; font-size:1.16rem; }
        .sales-transaction-heading { align-items:center; display:flex; flex:none; justify-content:space-between; padding:0 2px 5px; }
        .sales-transaction-heading h3 { color:#253943; font-size:.74rem; margin:0; }
        .sales-transaction-heading span { color:#829198; font-size:.57rem; }
        .sales-transaction-list { flex:1; min-height:0; overflow:auto; }
        .sales-transaction-row { align-items:center; border-bottom:1px solid #e3eceb; display:grid; gap:8px; grid-template-columns:38px minmax(0,1fr) 64px 76px 76px 26px; min-height:47px; padding:5px 3px; }
        .sales-transaction-row:hover { background:#f0f9f7; }
        .sales-transaction-row.is-voided { background:#fff4f3; }
        .sales-transaction-id { color:#638078; font-size:.64rem; font-weight:800; }
        .sales-transaction-customer { display:grid; min-width:0; }
        .sales-transaction-customer strong { color:#273a42; font-size:.66rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .sales-transaction-customer span { color:#839197; font-size:.56rem; }
        .sales-method { color:#567078; font-size:.57rem; font-weight:700; }
        .sales-transaction-status { background:#e8f7ef; border-radius:999px; color:#16734f; font-size:.52rem; font-weight:800; justify-self:start; padding:2px 6px; }
        .is-voided .sales-transaction-status { background:#fde4e2; color:#a7332c; }
        .sales-transaction-total { color:#1e3d41; font-size:.66rem; font-variant-numeric:tabular-nums; text-align:right; }
        .sales-receipt-link { align-items:center; border:1px solid #cbdedb; border-radius:5px; color:#157264; display:flex; font-size:.75rem; height:23px; justify-content:center; text-decoration:none; width:23px; }
        .sales-report-empty { align-items:center; color:#829198; display:flex; flex-direction:column; justify-content:center; min-height:145px; text-align:center; }
        .sales-report-empty>span { color:#65a89c; font-size:1.7rem; }
        .sales-report-empty strong { color:#40565e; font-size:.74rem; }
        .sales-report-empty p { font-size:.62rem; margin:2px 0; }
        .sr-only { height:1px; margin:-1px; overflow:hidden; position:absolute; width:1px; clip:rect(0,0,0,0); }
        @media(max-width:1100px) and (min-width:741px) { .product-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media(max-height:700px) and (min-width:741px) {
            .cashier-app-header { height:40px; }
            .cashier-app-main { height:calc(100vh - 48px); }
            .cashier-topbar { margin-bottom:4px; min-height:36px; }
            .cashier-topbar h1 { font-size:1.15rem; }
            .cashier-topbar-actions { gap:5px; }
            .report-quick-link { padding:5px 7px; }
            .cashier-action-tray { margin-top:6px; }
            .catalog-pane,.cart-pane { padding:8px; }
            .catalog-product-image { height:58px; }
            .catalog-product-details { padding:5px; }
            .cart-calculations { margin-top:3px; padding-top:3px; }
            .payment-section { margin-top:5px; padding-top:5px; }
        }
        @media(max-width:740px) {
            body.cashier-app-body { height:auto; min-height:100vh; overflow:auto; }
            .cashier-app-shell { height:auto; min-height:100vh; overflow:visible; padding:0 12px 16px; }
            .cashier-app-main { height:auto; min-height:calc(100vh - 54px); overflow:visible; }
            .cashier-app { min-height:calc(100vh - 54px); }
            .cashier-workspace { flex:none; grid-template-columns:1fr; }
            .catalog-pane { max-height:65vh; min-height:360px; }
            .scan-report-view { min-height:360px; }
            .sales-report-view { min-height:360px; }
            .scan-report-row { grid-template-columns:32px minmax(0,1fr) auto 70px 26px; }
            .sales-metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .sales-transaction-row { grid-template-columns:34px minmax(0,1fr) 54px 66px 68px 24px; }
            .sales-report-controls { flex-wrap:wrap; }
            .product-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .cart-pane { min-height:400px; position:static; }
            .cart-lines { flex:none; max-height:260px; min-height:90px; }
            .catalog-product-image { height:90px; }
        }
        @media(max-width:520px) {
            .cashier-app-shell { padding-left:10px; padding-right:10px; }
            .cashier-topbar { align-items:flex-start; flex-direction:row; gap:10px; }
            .shift-open-form { align-items:stretch; flex-direction:column; }
            .shift-open-form input { width:100%; }
            .product-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .catalog-pane,.cart-pane { padding:10px; }
            .catalog-product-image { height:82px; }
            .cashier-app-main { padding:0; }
            .cashier-topbar { flex-wrap:wrap; }
            .cashier-topbar-actions { flex-wrap:wrap; justify-content:flex-start; }
            .report-quick-link { font-size:.61rem; }
            .cashier-clock { display:none; }
            .catalog-view-switch { overflow-x:auto; }
            .catalog-view-tab { flex:none; }
            .cashier-action-tray { bottom:0; justify-content:space-around; margin:8px -2px 0; position:sticky; z-index:5; }
            .tray-action { flex:1; min-width:0; padding:7px 3px; }
            .tray-action span:last-child { font-size:.57rem; }
            .sales-transaction-row { grid-template-columns:31px minmax(0,1fr) auto 60px 24px; }
            .sales-method { display:none; }
        }
        @media(prefers-reduced-motion:reduce) {
            .catalog-product,.catalog-view-tab,.report-quick-link { transition:none; }
            .catalog-product:hover,.report-quick-link:hover { transform:none; }
        }
    </style>
@endsection
