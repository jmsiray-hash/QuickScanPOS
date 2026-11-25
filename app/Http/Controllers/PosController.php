<?php

namespace App\Http\Controllers;

use App\Models\CustomerCart;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PosController extends Controller
{
    private const READY_LABEL = 'SCANNED AND CHECKED ITEM - READY FOR PAYMENT';

    public function index(): View
    {
        return view('pos.index', [
            'cart' => $this->cart(),
        ]);
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
            ];
        }

        session([
            'pos_cart' => $posCart,
            'customer_cart_id' => $cart->id,
            'customer_cart_token' => $cart->token,
            'pos_verification_code' => $cart->verification_code,
        ]);

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
        if ($posCart === []) {
            return response()->json(['message' => 'Add items before generating a verification QR.'], 422);
        }

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
                    'token' => $this->newCode('CART-'),
                    'customer_name' => 'POS Checkout',
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
            'message' => 'Mobile item verification is ready.',
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

        $cart[$key] = [
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'quantity' => $quantity + 1,
            'unit_price' => (float) $product->selling_price,
            'available_stock' => $product->stock_quantity,
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
                ];
            }
            $cart[$key]['quantity'] = $quantity;
            $cart[$key]['available_stock'] = $product->stock_quantity;
        } else {
            return response()->json(['message' => 'Quantity exceeds stock or the product is inactive.'], 422);
        }

        session(['pos_cart' => $cart]);
        $this->syncLinkedCart($cart);

        return response()->json(['cart' => $this->cart()]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $cart = session('pos_cart', []);
        if ($cart === []) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $customerCartId = session('customer_cart_id');

        try {
            $sale = DB::transaction(function () use ($cart, $customerCartId, $request): Sale {
                $customerCart = null;
                if ($customerCartId !== null) {
                    $customerCart = CustomerCart::query()->lockForUpdate()->findOrFail($customerCartId);
                    if (
                        ! in_array($customerCart->status, ['ready_for_checkout', 'verified_ready'], true)
                        || ($customerCart->expires_at !== null && $customerCart->expires_at->isPast())
                    ) {
                        abort(422, 'The customer cart is no longer available for checkout.');
                    }

                    if ($customerCart->status !== 'verified_ready' || ! $customerCart->is_verified) {
                        abort(422, 'Scan and verify every item with the cashier phone before checkout.');
                    }
                }

                $subtotal = 0;
                $lockedProducts = [];
                foreach ($cart as $line) {
                    $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);
                    if (! $product->is_active || $product->stock_quantity < $line['quantity']) {
                        abort(422, 'Stock changed for '.$product->name.'.');
                    }

                    $unitPrice = $customerCartId !== null
                        ? (float) $line['unit_price']
                        : (float) $product->selling_price;
                    $lineTotal = $unitPrice * (int) $line['quantity'];
                    $subtotal += $lineTotal;
                    $lockedProducts[] = [$product, (int) $line['quantity'], $unitPrice, $lineTotal];
                }

                $sale = Sale::create([
                    'user_id' => $request->user()->id,
                    'subtotal' => $subtotal,
                    'tax' => 0,
                    'discount' => 0,
                    'total' => $subtotal,
                ]);

                foreach ($lockedProducts as [$product, $quantity, $unitPrice, $lineTotal]) {
                    $product->decrement('stock_quantity', $quantity);
                    $sale->items()->create([
                        'product_id' => $product->id,
                        'barcode' => $product->barcode,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                }

                if ($customerCart !== null) {
                    $customerCart->update(['status' => 'paid']);
                }

                return $sale;
            });
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'Checkout failed.',
            ], 422);
        }

        session()->forget([
            'pos_cart',
            'customer_cart_id',
            'customer_cart_token',
            'pos_verification_code',
        ]);

        return response()->json([
            'message' => 'Checkout completed.',
            'sale_id' => $sale->id,
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
        $subtotal = collect($items)->sum(
            static fn (array $item): float => (int) $item['quantity'] * (float) $item['unit_price'],
        );
        $customerCart = session('customer_cart_id')
            ? CustomerCart::query()->find(session('customer_cart_id'))
            : null;

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'tax' => 0,
            'discount' => 0,
            'total' => round($subtotal, 2),
            'customer_cart_token' => session('customer_cart_token'),
            'customer_name' => $customerCart?->customer_name,
            'is_verified' => (bool) ($customerCart?->is_verified ?? false),
            'verification_code' => session('pos_verification_code'),
        ];
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
