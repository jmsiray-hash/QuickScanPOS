<?php

namespace App\Http\Controllers;

use App\Models\CustomerCart;
use App\Models\CashierCustomerScan;
use App\Models\Customer;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PosController extends Controller
{
    private const READY_LABEL = 'SCANNED AND CHECKED ITEM - READY FOR PAYMENT';

    public function index(): View
    {
        return view('pos.index', [
            'cart' => $this->cart(),
            'activeShift' => CashierShift::query()->where('user_id', request()->user()->id)->where('status', 'open')->first(),
            'vatRate' => (float) config('pos.vat_rate', 0),
        ]);
    }

    public function cartContents(): JsonResponse
    {
        return response()->json(['cart' => $this->cart()]);
    }

    public function storeQr(): View
    {
        return view('pos.store-qr', [
            'shopUrl' => route('shop.entry'),
        ]);
    }

    public function importCart(string $token): JsonResponse
    {
        $cart = CustomerCart::query()->where('token', strtoupper($token))->firstOrFail();

        if ($cart->expires_at !== null && $cart->expires_at->isPast()) {
            $cart->update(['status' => 'expired']);

            return response()->json(['message' => 'This customer cart has expired.'], 410);
        }

        if (! in_array($cart->status, ['ready_for_checkout', 'verified_ready'], true)) {
            return response()->json(['message' => 'This cart is not ready for cashier checkout.'], 422);
        }

        $cart->load('items.product');
        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'This customer cart has no items.'], 422);
        }

        $posCart = [];
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product->is_active || $product->stock_quantity < $item->qty) {
                return response()->json([
                    'message' => 'Stock is no longer available for '.$product->name.'.',
                ], 422);
            }

            $posCart[(string) $product->id] = [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'quantity' => $item->qty,
                'unit_price' => (float) $item->price,
                'available_stock' => $product->stock_quantity,
                'discount_type' => null,
                'discount_percent' => 0,
                'promo_price' => null,
                'senior_pwd_eligible' => $product->senior_pwd_eligible,
            ];
        }

        CashierCustomerScan::create([
            'cashier_id' => request()->user()->id,
            'customer_cart_id' => $cart->id,
            'cart_token' => $cart->token,
            'customer_name' => $cart->customer_name,
            'item_count' => $cart->items->sum('qty'),
            'scanned_at' => now(),
        ]);

        session()->forget('pos_customer_id');
        session([
            'pos_cart' => $posCart,
            'customer_cart_id' => $cart->id,
            'customer_cart_token' => $cart->token,
            'pos_verification_code' => $cart->verification_code,
        ]);
        if ($cart->customer_id !== null) {
            session(['pos_customer_id' => $cart->customer_id]);
        }

        return response()->json([
            'message' => 'Customer cart loaded.',
            'cart' => $this->cart(),
            'customer_name' => $cart->customer_name,
            'customer_cart_token' => $cart->token,
            'verification_code' => $cart->verification_code,
            'is_verified' => $cart->is_verified,
            'verification_url' => $cart->verification_code
                ? route('pos.verify', $cart->verification_code)
                : null,
            'status_url' => $cart->verification_code
                ? route('pos.verify-status', $cart->verification_code)
                : null,
        ]);
    }

    public function generateVerification(): JsonResponse
    {
        $posCart = session('pos_cart', []);

        $cartId = session('customer_cart_id');
        $customerCart = null;
        $verificationCode = null;

        DB::transaction(function () use ($cartId, $posCart, &$customerCart, &$verificationCode): void {
            if ($cartId !== null) {
                $customerCart = CustomerCart::query()->lockForUpdate()->findOrFail($cartId);
                if (in_array($customerCart->status, ['paid', 'expired'], true)) {
                    abort(409, 'This customer cart can no longer be verified.');
                }
            } else {
                $customerCart = CustomerCart::create([
                    'customer_id' => session('pos_customer_id'),
                    'token' => $this->newCode('CART-'),
                    'customer_name' => Customer::query()->find(session('pos_customer_id'))?->full_name ?? 'POS Checkout',
                    'status' => 'ready_for_checkout',
                    'expires_at' => now()->addHours(2),
                ]);
            }

            $existingItems = $customerCart->items()->get()->keyBy('product_id');
            $activeIds = [];

            foreach ($posCart as $line) {
                $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);
                $quantity = (int) $line['quantity'];
                if (! $product->is_active || $quantity < 1 || $quantity > $product->stock_quantity) {
                    abort(422, 'The cart contains an item that is no longer available.');
                }

                $activeIds[] = $product->id;
                $item = $existingItems->get($product->id) ?? $customerCart->items()->make([
                    'product_id' => $product->id,
                ]);
                $item->fill([
                    'qty' => $quantity,
                    'price' => $line['unit_price'],
                    'checked_qty' => 0,
                    'is_checked' => false,
                ]);
                $item->save();
            }

            $customerCart->items()->whereNotIn('product_id', $activeIds)->delete();
            $verificationCode = $this->newCode('VERIFY-');
            $customerCart->update([
                'verification_code' => $verificationCode,
                'status' => 'ready_for_checkout',
                'is_verified' => false,
                'verified_at' => null,
            ]);
        });

        session([
            'customer_cart_id' => $customerCart->id,
            'customer_cart_token' => $customerCart->token,
            'pos_verification_code' => $verificationCode,
        ]);

        return response()->json([
            'message' => $posCart === []
                ? 'Cashier phone connected. Add items at the register; the phone will update automatically.'
                : 'Mobile item verification is ready.',
            'customer_cart_token' => $customerCart->token,
            'verification_code' => $verificationCode,
            'verification_url' => route('pos.verify', $verificationCode),
            'status_url' => route('pos.verify-status', $verificationCode),
            'ready_label' => self::READY_LABEL,
        ]);
    }

    public function scanItem(Request $request): JsonResponse
    {
        $request->merge([
            'code' => $request->input('code', $request->input('barcode')),
        ]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);
        $code = $this->normalizeScanCode($validated['code']);
        if (str_starts_with($code, 'CART-')) {
            return $this->importCart($code);
        }
        if (str_starts_with($code, 'VERIFY-')) {
            $cart = CustomerCart::query()
                ->where('verification_code', $code)
                ->firstOrFail();

            return $this->importCart($cart->token);
        }

        $product = Product::query()->whereRaw('UPPER(barcode) = ?', [$code])->first();
        if ($product === null) {
            return response()->json(['message' => 'Barcode or printed product code not found.'], 404);
        }
        if (! $product->is_active) {
            return response()->json(['message' => 'This product is inactive.'], 422);
        }
        if ($product->stock_quantity < 1) {
            return response()->json(['message' => 'This product is out of stock.'], 422);
        }

        $cart = session('pos_cart', []);
        $key = (string) $product->id;
        $quantity = (int) ($cart[$key]['quantity'] ?? 0);
        if ($quantity >= $product->stock_quantity) {
            return response()->json(['message' => 'Cart quantity cannot exceed available stock.'], 422);
        }

        $existingLine = $cart[$key] ?? [];
        $cart[$key] = [
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'quantity' => $quantity + 1,
            'unit_price' => (float) $product->selling_price,
            'available_stock' => $product->stock_quantity,
            'discount_type' => $existingLine['discount_type'] ?? null,
            'discount_percent' => $existingLine['discount_percent'] ?? 0,
            'promo_price' => $existingLine['promo_price'] ?? null,
            'senior_pwd_eligible' => $product->senior_pwd_eligible,
        ];
        session(['pos_cart' => $cart]);
        $this->syncLinkedCart($cart);

        return response()->json([
            'message' => 'Added: '.$product->name,
            'cart' => $this->cart(),
        ]);
    }

    public function updateCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0'],
            'discount_type' => ['sometimes', 'nullable', 'in:store'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'promo_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $cart = session('pos_cart', []);
        $key = (string) $product->id;
        $quantity = (int) $validated['quantity'];

        if ($quantity === 0) {
            unset($cart[$key]);
        } elseif ($quantity <= $product->stock_quantity && $product->is_active) {
            if (! isset($cart[$key])) {
                $cart[$key] = [
                    'product_id' => $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'unit_price' => (float) $product->selling_price,
                    'discount_type' => null,
                    'discount_percent' => 0,
                    'promo_price' => null,
                ];
            }
            $cart[$key]['quantity'] = $quantity;
            $cart[$key]['available_stock'] = $product->stock_quantity;
            if (array_key_exists('discount_type', $validated)) {
                $cart[$key]['discount_type'] = $validated['discount_type'] ?: null;
                if ($cart[$key]['discount_type'] === null) {
                    $cart[$key]['discount_percent'] = 0;
                }
            }
            if (array_key_exists('discount_percent', $validated) && ($cart[$key]['discount_type'] ?? null) !== null) {
                $cart[$key]['discount_percent'] = (float) $validated['discount_percent'];
            }
            if (array_key_exists('promo_price', $validated)) {
                $promoPrice = $validated['promo_price'] === null ? null : (float) $validated['promo_price'];
                $regularPrice = (float) ($cart[$key]['unit_price'] ?? $product->selling_price);
                if ($promoPrice !== null && $promoPrice >= $regularPrice) {
                    return response()->json(['message' => 'Promo price must be lower than the regular selling price.'], 422);
                }
                $cart[$key]['promo_price'] = $promoPrice;
            }
            $cart[$key]['senior_pwd_eligible'] = $product->senior_pwd_eligible;
            $this->assertDiscountEligible($cart[$key]);
        } else {
            return response()->json(['message' => 'Quantity exceeds stock or the product is inactive.'], 422);
        }

        session(['pos_cart' => $cart]);
        $this->syncLinkedCart($cart);

        return response()->json(['cart' => $this->cart()]);
    }

    public function selectCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
        $customerCartId = session('customer_cart_id');
        if ($customerCartId !== null) {
            $cartCustomerId = CustomerCart::query()->findOrFail($customerCartId)->customer_id;
            if ($cartCustomerId !== null && (int) $cartCustomerId !== (int) ($validated['customer_id'] ?? 0)) {
                return response()->json(['message' => 'This Scan & Go cart belongs to another customer.'], 422);
            }
        }

        $customerId = $validated['customer_id'] ?? null;
        if ((int) session('pos_customer_id') !== (int) $customerId) {
            $cart = session('pos_cart', []);
            foreach ($cart as &$line) {
                $line['discount_type'] = null;
                $line['discount_percent'] = 0;
            }
            unset($line);
            session(['pos_cart' => $cart]);
        }

        if ($customerId === null) {
            session()->forget('pos_customer_id');
        } else {
            session(['pos_customer_id' => $customerId]);
        }

        return response()->json([
            'message' => $customerId === null ? 'Customer selection cleared.' : 'Customer selected.',
            'cart' => $this->cart(),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $cart = session('pos_cart', []);
        if ($cart === []) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,gcash,maya,card'],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_reference' => ['required_if:payment_method,gcash,maya', 'nullable', 'string', 'max:100'],
            'special_discount_type' => ['nullable', 'in:senior,pwd'],
            'special_discount_id_number' => ['required_with:special_discount_type', 'nullable', 'string', 'max:100'],
        ]);
        if ($validated['payment_method'] === 'cash' && ! isset($validated['cash_received'])) {
            return response()->json(['message' => 'Enter the cash amount received.'], 422);
        }
        if (! CashierShift::query()->where('user_id', $request->user()->id)->where('status', 'open')->exists()) {
            return response()->json(['message' => 'Open a shift and record the opening cash fund before completing a sale.'], 409);
        }

        $customerCartId = session('customer_cart_id');

        try {
            $sale = DB::transaction(function () use ($cart, $customerCartId, $request, $validated): Sale {
                $shift = CashierShift::query()
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
                if ($shift === null) {
                    abort(409, 'Open a shift and record the opening cash fund before completing a sale.');
                }

                $customerCart = null;
                if ($customerCartId !== null) {
                    $customerCart = CustomerCart::query()->lockForUpdate()->findOrFail($customerCartId);
                    if (
                        ! in_array($customerCart->status, ['ready_for_checkout', 'verified_ready'], true)
                        || ($customerCart->expires_at !== null && $customerCart->expires_at->isPast())
                    ) {
                        abort(422, 'The customer cart is no longer available for checkout.');
                    }

                }

                $customerId = $customerCart?->customer_id ?? session('pos_customer_id');
                if (
                    $customerCart?->customer_id !== null
                    && (int) session('pos_customer_id') !== (int) $customerCart->customer_id
                ) {
                    abort(422, 'The selected customer does not match the Scan & Go account.');
                }
                $customer = $customerId !== null
                    ? Customer::query()->lockForUpdate()->findOrFail($customerId)
                    : null;
                $remainingStoreDiscount = $customer
                    ? max(0, (float) $customer->monthly_discount_limit - $this->monthlyStoreDiscountUsed($customer))
                    : 0;
                $subtotal = 0.0;
                $discountTotal = 0.0;
                $taxTotal = 0.0;
                $saleTotal = 0.0;
                $specialDiscountApplied = false;
                $lockedProducts = [];
                $vatRate = (float) config('pos.vat_rate', 0);
                if ($vatRate < 0 || $vatRate > 100) {
                    throw new \RuntimeException('POS_VAT_RATE must be between 0 and 100.');
                }
                $vatDivider = 1 + ($vatRate / 100);
                $specialDiscountType = $validated['special_discount_type'] ?? null;
                foreach ($cart as $line) {
                    $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);
                    if (! $product->is_active || $product->stock_quantity < $line['quantity']) {
                        abort(422, 'Stock changed for '.$product->name.'.');
                    }

                    $unitPrice = $customerCartId !== null
                        ? (float) $line['unit_price']
                        : (float) $product->selling_price;
                    $quantity = (int) $line['quantity'];
                    $grossLineTotal = round($unitPrice * $quantity, 2);
                    $promoUnitPrice = isset($line['promo_price']) ? (float) $line['promo_price'] : null;
                    if ($promoUnitPrice !== null && ($promoUnitPrice < 0 || $promoUnitPrice >= $unitPrice)) {
                        abort(422, 'Promo price for '.$product->name.' must be below its regular price.');
                    }
                    $appliedUnitPrice = $promoUnitPrice ?? $unitPrice;
                    $appliedGrossTotal = round($appliedUnitPrice * $quantity, 2);
                    $originalBase = round($grossLineTotal / $vatDivider, 2);
                    $appliedBase = round($appliedGrossTotal / $vatDivider, 2);
                    $promoDiscount = round($originalBase - $appliedBase, 2);
                    $eligibleForSpecial = $specialDiscountType !== null
                        && $product->senior_pwd_eligible
                        && $promoUnitPrice === null;

                    $discountType = $promoUnitPrice !== null ? 'promo' : ($line['discount_type'] ?? null);
                    $discountPercent = $promoUnitPrice !== null
                        ? ($unitPrice > 0 ? round((($unitPrice - $promoUnitPrice) / $unitPrice) * 100, 2) : 0)
                        : (float) ($line['discount_percent'] ?? 0);
                    $this->assertDiscountEligible($line, $customer);
                    if ($eligibleForSpecial) {
                        $specialDiscountApplied = true;
                        $discountType = $specialDiscountType;
                        $discountPercent = (float) config('pos.senior_pwd_discount_rate', 20);
                        $discountAmount = round($appliedBase * $discountPercent / 100, 2);
                        $lineTax = 0.0;
                        $lineTotal = round($appliedBase - $discountAmount, 2);
                    } else {
                        $discountAmount = $promoUnitPrice === null
                            ? round($appliedBase * $discountPercent / 100, 2)
                            : 0.0;
                        if ($discountType === 'store') {
                            $discountAmount = min($discountAmount, $remainingStoreDiscount);
                            $remainingStoreDiscount = round($remainingStoreDiscount - $discountAmount, 2);
                        }
                        $netBase = round($appliedBase - $discountAmount, 2);
                        $lineTax = round($netBase * $vatRate / 100, 2);
                        $lineTotal = round($netBase + $lineTax, 2);
                    }
                    $discountTotal += $promoDiscount + $discountAmount;
                    $taxTotal += $lineTax;
                    $subtotal += $originalBase;
                    $saleTotal += $lineTotal;
                    $lockedProducts[] = [
                        $product,
                        $quantity,
                        $unitPrice,
                        $promoUnitPrice,
                        $lineTotal,
                        $discountType,
                        $discountPercent,
                        round($promoDiscount + $discountAmount, 2),
                    ];
                }

                if ($specialDiscountType !== null && ! $specialDiscountApplied) {
                    abort(422, 'No products in this cart are marked eligible for the Senior/PWD discount.');
                }

                $subtotal = round($subtotal, 2);
                $discountTotal = round($discountTotal, 2);
                $taxTotal = round($taxTotal, 2);
                $saleTotal = round($saleTotal, 2);
                $cashReceived = $validated['payment_method'] === 'cash'
                    ? (float) $validated['cash_received']
                    : null;
                if ($cashReceived !== null && $cashReceived < $saleTotal) {
                    abort(422, 'Cash received must be at least ₱'.number_format($saleTotal, 2).'.');
                }

                $storeSettings = StoreSetting::current();
                $sale = Sale::create([
                    'user_id' => $request->user()->id,
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->full_name ?? $customerCart?->customer_name,
                    'shift_id' => $shift->id,
                    'store_name' => $storeSettings->store_name,
                    'store_address' => $storeSettings->store_address,
                    'subtotal' => $subtotal,
                    'tax' => $taxTotal,
                    'discount' => $discountTotal,
                    'total' => $saleTotal,
                    'payment_method' => $validated['payment_method'],
                    'cash_received' => $cashReceived,
                    'change_due' => $cashReceived === null ? 0 : round($cashReceived - $saleTotal, 2),
                    'payment_reference' => $validated['payment_reference'] ?? null,
                    'special_discount_type' => $specialDiscountType,
                    'special_discount_id_number' => $validated['special_discount_id_number'] ?? null,
                ]);

                foreach ($lockedProducts as [
                    $product,
                    $quantity,
                    $unitPrice,
                    $promoUnitPrice,
                    $lineTotal,
                    $discountType,
                    $discountPercent,
                    $discountAmount,
                ]) {
                    $product->decrement('stock_quantity', $quantity);
                    $sale->items()->create([
                        'product_id' => $product->id,
                        'barcode' => $product->barcode,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'promo_unit_price' => $promoUnitPrice,
                        'is_promo' => $promoUnitPrice !== null,
                        'discount_type' => $discountType,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                        'line_total' => $lineTotal,
                    ]);
                }

                if ($customerCart !== null) {
                    $customerCart->update([
                        'status' => 'paid',
                        'sale_id' => $sale->id,
                    ]);
                }

                return $sale;
            });
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'Checkout failed.',
            ], $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 422);
        }

        session()->forget([
            'pos_cart',
            'customer_cart_id',
            'customer_cart_token',
            'pos_verification_code',
            'pos_customer_id',
        ]);

        $sale->load(['user', 'items']);
        return response()->json([
            'message' => 'Checkout completed.',
            'sale_id' => $sale->id,
            'receipt_url' => route('sales.show', $sale),
            'receipt' => [
                'id' => $sale->id,
                'store' => config('app.name'),
                'store_name' => $sale->store_name,
                'store_address' => $sale->store_address,
                'created_at' => $sale->created_at->format('M j, Y g:i A'),
                'cashier' => $sale->user?->name ?? 'Cashier',
                'customer_name' => $sale->customer_name,
                'subtotal' => (float) $sale->subtotal,
                'tax' => (float) $sale->tax,
                'discount' => (float) $sale->discount,
                'total' => (float) $sale->total,
                'payment_method' => $sale->payment_method,
                'payment_reference' => $sale->payment_reference,
                'special_discount_type' => $sale->special_discount_type,
                'special_discount_id_number' => $sale->special_discount_id_number,
                'cash_received' => $sale->cash_received === null ? null : (float) $sale->cash_received,
                'change_due' => (float) $sale->change_due,
                'items' => $sale->items->map(static fn ($item): array => [
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'promo_unit_price' => $item->promo_unit_price === null ? null : (float) $item->promo_unit_price,
                    'is_promo' => $item->is_promo,
                    'discount_amount' => (float) $item->discount_amount,
                    'line_total' => (float) $item->line_total,
                ])->values(),
            ],
            'cart' => $this->cart(),
        ]);
    }

    private function syncLinkedCart(array $posCart): void
    {
        $cartId = session('customer_cart_id');
        if ($cartId === null) {
            return;
        }

        $customerCart = CustomerCart::query()->find($cartId);
        if ($customerCart === null || in_array($customerCart->status, ['paid', 'expired'], true)) {
            return;
        }

        DB::transaction(function () use ($customerCart, $posCart): void {
            $cart = CustomerCart::query()->lockForUpdate()->findOrFail($customerCart->id);
            $existingItems = $cart->items()->get()->keyBy('product_id');
            $activeIds = [];
            $changed = false;

            foreach ($posCart as $line) {
                $productId = (int) $line['product_id'];
                $activeIds[] = $productId;
                $item = $existingItems->get($productId);
                if ($item === null) {
                    $item = $cart->items()->make(['product_id' => $productId]);
                    $changed = true;
                } elseif (
                    (int) $item->qty !== (int) $line['quantity']
                    || (float) $item->price !== (float) $line['unit_price']
                ) {
                    $changed = true;
                }

                $item->fill([
                    'qty' => (int) $line['quantity'],
                    'price' => (float) $line['unit_price'],
                ]);
                $item->save();
            }

            if ($existingItems->keys()->diff($activeIds)->isNotEmpty()) {
                $cart->items()->whereNotIn('product_id', $activeIds)->delete();
                $changed = true;
            }

            if ($changed) {
                $cart->items()->update([
                    'checked_qty' => 0,
                    'is_checked' => false,
                ]);
                $cart->update([
                    'is_verified' => false,
                    'verified_at' => null,
                    'status' => 'ready_for_checkout',
                ]);
            }
        });
    }

    private function cart(): array
    {
        $items = array_values(session('pos_cart', []));
        $customerCart = session('customer_cart_id')
            ? CustomerCart::query()->find(session('customer_cart_id'))
            : null;
        $customer = session('pos_customer_id')
            ? Customer::query()->find(session('pos_customer_id'))
            : $customerCart?->customer;
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;
        $total = 0.0;
        $remaining = $customer
            ? max(0, (float) $customer->monthly_discount_limit - $this->monthlyStoreDiscountUsed($customer))
            : 0;
        $vatRate = (float) config('pos.vat_rate', 0);
        $vatDivider = 1 + ($vatRate / 100);

        foreach ($items as &$item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $promoPrice = isset($item['promo_price']) ? (float) $item['promo_price'] : null;
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $appliedGross = round($quantity * ($promoPrice ?? $unitPrice), 2);
            $originalBase = round($lineSubtotal / $vatDivider, 2);
            $appliedBase = round($appliedGross / $vatDivider, 2);
            $promoDiscount = round($originalBase - $appliedBase, 2);
            $lineDiscount = $promoPrice === null
                ? round($appliedBase * (float) ($item['discount_percent'] ?? 0) / 100, 2)
                : 0;
            if (($item['discount_type'] ?? null) === 'store') {
                $lineDiscount = min($lineDiscount, $remaining);
                $remaining = round($remaining - $lineDiscount, 2);
            }
            $netBase = round($appliedBase - $lineDiscount, 2);
            $item['promo_price'] = $promoPrice;
            $item['is_promo'] = $promoPrice !== null;
            $item['promo_discount'] = $promoDiscount;
            $item['senior_pwd_eligible'] = (bool) ($item['senior_pwd_eligible'] ?? false);
            $item['discount_amount'] = round($promoDiscount + $lineDiscount, 2);
            $item['tax_amount'] = round($netBase * $vatRate / 100, 2);
            $item['line_total'] = round($netBase + $item['tax_amount'], 2);
            $subtotal += $originalBase;
            $discount += $promoDiscount + $lineDiscount;
            $tax += $item['tax_amount'];
            $total += $item['line_total'];
        }
        unset($item);

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'vat_rate' => $vatRate,
            'customer_cart_token' => session('customer_cart_token'),
            'customer_name' => $customer?->full_name ?? $customerCart?->customer_name,
            'customer_id' => $customer?->id,
            'customer_discount_id_type' => $customer?->discount_id_type,
            'customer_discount_id_number' => $customer?->discount_id_number,
            'customer_store_discount_reminder' => (bool) ($customer?->store_discount_reminder ?? false),
            'monthly_discount_limit' => (float) ($customer?->monthly_discount_limit ?? 0),
            'monthly_discount_used' => $customer ? $this->monthlyStoreDiscountUsed($customer) : 0,
            'monthly_discount_remaining' => $customer
                ? max(0, (float) $customer->monthly_discount_limit - $this->monthlyStoreDiscountUsed($customer))
                : 0,
            'is_verified' => (bool) ($customerCart?->is_verified ?? false),
            'verification_code' => session('pos_verification_code'),
        ];
    }

    private function monthlyStoreDiscountUsed(Customer $customer): float
    {
        return (float) \App\Models\SaleItem::query()
            ->where('discount_type', 'store')
            ->whereHas('sale', function ($query) use ($customer): void {
                $query->where('customer_id', $customer->id)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            })
            ->sum('discount_amount');
    }

    private function assertDiscountEligible(array $line, ?Customer $customer = null): void
    {
        $type = $line['discount_type'] ?? null;
        $percent = (float) ($line['discount_percent'] ?? 0);
        if ($type === null || $percent === 0.0) {
            return;
        }

        $customer ??= session('pos_customer_id')
            ? Customer::query()->find(session('pos_customer_id'))
            : null;
        if ($customer === null) {
            abort(422, 'Select a customer before applying a discount.');
        }
        if (in_array($type, ['pwd', 'senior'], true)) {
            if ($customer->discount_id_type !== $type || blank($customer->discount_id_number)) {
                abort(422, 'The customer does not have a recorded '.strtoupper($type).' ID.');
            }
        }
    }

    private function normalizeScanCode(string $rawCode): string
    {
        $code = strtoupper(trim($rawCode));
        if (str_starts_with($code, 'CART-') || str_starts_with($code, 'VERIFY-')) {
            return $code;
        }

        $parts = parse_url(trim($rawCode));
        if (is_array($parts)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidates = array_merge(
                array_values(array_filter([
                    $query['cart'] ?? null,
                    $query['token'] ?? null,
                ])),
                array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/')))),
            );

            foreach (array_reverse($candidates) as $candidate) {
                $candidate = strtoupper(urldecode((string) $candidate));
                if (str_starts_with($candidate, 'CART-') || str_starts_with($candidate, 'VERIFY-')) {
                    return $candidate;
                }
            }
        }

        return $code;
    }

    private function newCode(string $prefix): string
    {
        do {
            $code = $prefix.Str::upper(Str::random(12));
        } while (CustomerCart::query()->where('token', $code)->orWhere('verification_code', $code)->exists());

        return $code;
    }
}
