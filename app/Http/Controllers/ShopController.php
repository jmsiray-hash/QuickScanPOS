<?php

namespace App\Http\Controllers;

use App\Models\CustomerCart;
use App\Models\CustomerCartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function entry(): View
    {
        return view('shop.entry');
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
        ]);

        $cart = CustomerCart::create([
            'token' => $this->newCode('CART-'),
            'customer_name' => trim($validated['customer_name']),
            'status' => 'shopping',
            'expires_at' => now()->addHours(2),
        ]);

        return to_route('shop.cart', $cart->token);
    }

    public function show(string $token): View
    {
        $cart = $this->findCart($token);
        $this->expireIfNeeded($cart);

        return view('shop.cart', [
            'cart' => $cart,
            'cartData' => $this->cartData($cart),
        ]);
    }

    public function scan(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);

        $cart = $this->shoppingCart($token);
        $code = strtoupper(trim($validated['code']));
        $product = Product::query()
            ->whereRaw('UPPER(barcode) = ?', [$code])
            ->first();

        if ($product === null) {
            return response()->json(['message' => 'No product matches that barcode or printed code.'], 404);
        }

        if (! $product->is_active) {
            return response()->json(['message' => 'This product is inactive.'], 422);
        }

        DB::transaction(function () use ($cart, $product): void {
            $lockedCart = CustomerCart::query()->lockForUpdate()->findOrFail($cart->id);
            $this->assertShopping($lockedCart);

            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! $lockedProduct->is_active || $lockedProduct->stock_quantity < 1) {
                abort(422, 'This product is out of stock.');
            }

            $item = $lockedCart->items()->firstOrNew(['product_id' => $lockedProduct->id]);
            $currentQuantity = (int) ($item->qty ?? 0);
            if ($currentQuantity >= $lockedProduct->stock_quantity) {
                abort(422, 'Cart quantity cannot exceed available stock.');
            }

            $item->qty = $currentQuantity + 1;
            if (! $item->exists) {
                $item->price = $lockedProduct->selling_price;
            }
            $item->checked_qty = 0;
            $item->is_checked = false;
            $item->save();
        });

        return response()->json([
            'message' => 'Added to your cart.',
            'cart' => $this->cartData($cart->fresh()),
        ]);
    }

    public function updateItem(Request $request, string $token, CustomerCartItem $item): JsonResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0'],
        ]);

        $cart = $this->shoppingCart($token);
        abort_unless($item->customer_cart_id === $cart->id, 404);

        DB::transaction(function () use ($cart, $item, $validated): void {
            $lockedCart = CustomerCart::query()->lockForUpdate()->findOrFail($cart->id);
            $this->assertShopping($lockedCart);
            $lockedItem = $lockedCart->items()->lockForUpdate()->findOrFail($item->id);

            if ((int) $validated['qty'] === 0) {
                $lockedItem->delete();

                return;
            }

            $product = Product::query()->lockForUpdate()->findOrFail($lockedItem->product_id);
            if (! $product->is_active || (int) $validated['qty'] > $product->stock_quantity) {
                abort(422, 'Requested quantity exceeds available stock.');
            }

            $lockedItem->update([
                'qty' => (int) $validated['qty'],
                'checked_qty' => 0,
                'is_checked' => false,
            ]);
        });

        return response()->json([
            'cart' => $this->cartData($cart->fresh()),
        ]);
    }

    public function finalize(string $token): JsonResponse
    {
        $cart = $this->shoppingCart($token);

        DB::transaction(function () use ($cart): void {
            $lockedCart = CustomerCart::query()->lockForUpdate()->findOrFail($cart->id);
            $this->expireIfNeeded($lockedCart);
            $this->assertShopping($lockedCart);
            if (! $lockedCart->items()->exists()) {
                abort(422, 'Add at least one item before finishing.');
            }
            $lockedCart->update(['status' => 'ready_for_checkout']);
        });

        return response()->json([
            'message' => 'Your cashier QR is ready.',
            'qr_value' => $cart->token,
            'cart' => $this->cartData($cart->fresh()),
        ]);
    }

    private function findCart(string $token): CustomerCart
    {
        return CustomerCart::query()
            ->where('token', strtoupper($token))
            ->firstOrFail();
    }

    private function shoppingCart(string $token): CustomerCart
    {
        $cart = $this->findCart($token);
        $this->expireIfNeeded($cart);
        $this->assertShopping($cart);

        return $cart;
    }

    private function expireIfNeeded(CustomerCart $cart): void
    {
        if ($cart->expires_at !== null && $cart->expires_at->isPast() && $cart->status !== 'paid') {
            $cart->update(['status' => 'expired']);
            abort(410, 'This shopping cart has expired.');
        }
    }

    private function assertShopping(CustomerCart $cart): void
    {
        if ($cart->status !== 'shopping') {
            abort(409, 'This cart is no longer open for changes.');
        }
    }

    private function cartData(CustomerCart $cart): array
    {
        $items = $cart->items()->with('product')->get()->map(static function (CustomerCartItem $item): array {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'code' => $item->product->barcode,
                'name' => $item->product->name,
                'qty' => $item->qty,
                'checked_qty' => $item->checked_qty,
                'is_checked' => $item->is_checked,
                'price' => (float) $item->price,
                'line_total' => round($item->qty * (float) $item->price, 2),
                'stock_quantity' => $item->product->stock_quantity,
            ];
        })->values();

        $totalPieces = (int) $items->sum('qty');
        $grandTotal = round((float) $items->sum('line_total'), 2);

        return [
            'token' => $cart->token,
            'customer_name' => $cart->customer_name,
            'status' => $cart->status,
            'is_verified' => $cart->is_verified,
            'verification_code' => $cart->verification_code,
            'items' => $items,
            'total_pieces' => $totalPieces,
            'grand_total' => $grandTotal,
        ];
    }

    private function newCode(string $prefix): string
    {
        do {
            $code = $prefix.Str::upper(Str::random(12));
        } while (CustomerCart::query()->where('token', $code)->exists());

        return $code;
    }
}
