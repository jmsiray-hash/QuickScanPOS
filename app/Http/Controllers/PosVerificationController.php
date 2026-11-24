<?php

namespace App\Http\Controllers;

use App\Models\CustomerCart;
use App\Models\CustomerCartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosVerificationController extends Controller
{
    private const READY_LABEL = 'SCANNED AND CHECKED ITEM - READY FOR PAYMENT';

    public function show(string $code): View
    {
        $cart = $this->findByCode($code);
        $this->expireIfNeeded($cart);

        return view('pos.verify', [
            'cart' => $cart,
            'cartData' => $this->cartData($cart),
            'readyLabel' => self::READY_LABEL,
        ]);
    }

    public function checkItem(Request $request, string $code): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);

        $cart = $this->findByCode($code);
        $this->expireIfNeeded($cart);
        if (! in_array($cart->status, ['ready_for_checkout', 'verified_ready'], true)) {
            return response()->json(['message' => 'This cart is not awaiting item verification.'], 409);
        }

        $productCode = strtoupper(trim($validated['code']));
        $product = Product::query()->whereRaw('UPPER(barcode) = ?', [$productCode])->first();

        if ($product === null) {
            return response()->json(['message' => 'Mismatch: no product matches that barcode or printed code.'], 422);
        }

        $message = 'Item checked.';

        DB::transaction(function () use ($cart, $product, &$message): void {
            $lockedCart = CustomerCart::query()->lockForUpdate()->findOrFail($cart->id);
            if (! in_array($lockedCart->status, ['ready_for_checkout', 'verified_ready'], true)) {
                abort(409, 'This cart is not awaiting item verification.');
            }

            $item = $lockedCart->items()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                abort(422, 'Mismatch: this product is not in the expected cart.');
            }

            if ($item->checked_qty >= $item->qty) {
                abort(422, 'Mismatch: the expected quantity for this item has already been checked.');
            }

            $item->checked_qty++;
            $item->is_checked = $item->checked_qty === $item->qty;
            $item->save();

            $allChecked = $lockedCart->items()->whereColumn('checked_qty', '<', 'qty')->doesntExist()
                && $lockedCart->items()->exists();

            $lockedCart->update([
                'is_verified' => $allChecked,
                'verified_at' => $allChecked ? now() : null,
                'status' => $allChecked ? 'verified_ready' : 'ready_for_checkout',
            ]);

            if ($allChecked) {
                $message = self::READY_LABEL;
            }
        });

        $cart = $cart->fresh();

        return response()->json([
            'message' => $message,
            'label' => $cart->is_verified ? self::READY_LABEL : null,
            'cart' => $this->cartData($cart),
        ]);
    }

    public function status(string $code): JsonResponse
    {
        $cart = $this->findByCode($code);
        $this->expireIfNeeded($cart);

        return response()->json([
            'is_verified' => $cart->is_verified,
            'status' => $cart->status,
            'label' => $cart->is_verified ? self::READY_LABEL : null,
            'cart' => $this->cartData($cart),
        ]);
    }

    private function findByCode(string $code): CustomerCart
    {
        return CustomerCart::query()
            ->where('verification_code', strtoupper($code))
            ->firstOrFail();
    }

    private function expireIfNeeded(CustomerCart $cart): void
    {
        if ($cart->expires_at !== null && $cart->expires_at->isPast() && $cart->status !== 'paid') {
            $cart->update(['status' => 'expired']);
            abort(410, 'This verification session has expired.');
        }
    }

    private function cartData(CustomerCart $cart): array
    {
        $items = $cart->items()->with('product')->get()->map(static function (CustomerCartItem $item): array {
            return [
                'product_id' => $item->product_id,
                'code' => $item->product->barcode,
                'name' => $item->product->name,
                'qty' => $item->qty,
                'checked_qty' => $item->checked_qty,
                'is_checked' => $item->is_checked,
                'price' => (float) $item->price,
            ];
        })->values();

        $totalExpectedPieces = (int) $items->sum('qty');
        $totalCheckedPieces = (int) $items->sum('checked_qty');
        $allChecked = $totalExpectedPieces > 0 && $totalCheckedPieces === $totalExpectedPieces;

        return [
            'customer_name' => $cart->customer_name,
            'token' => $cart->token,
            'status' => $cart->status,
            'is_verified' => $cart->is_verified,
            'items' => $items,
            'total_expected_pieces' => $totalExpectedPieces,
            'total_checked_pieces' => $totalCheckedPieces,
            'all_items_checked' => $allChecked,
            'ready_label' => $allChecked ? self::READY_LABEL : null,
        ];
    }
}
