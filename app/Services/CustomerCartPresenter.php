<?php

namespace App\Services;

use App\Models\CustomerCart;
use App\Models\StoreSetting;

class CustomerCartPresenter
{
    public static function make(CustomerCart $cart): array
    {
        $cart->loadMissing(['items.product', 'sale.items']);
        $items = $cart->items->map(static function ($item): array {
            $product = $item->product;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'code' => $product?->barcode ?? '',
                'name' => $product?->name ?? 'Removed product',
                'image_url' => $product?->image_path
                    ? route('products.image', $product)
                    : null,
                'qty' => $item->qty,
                'checked_qty' => $item->checked_qty,
                'is_checked' => $item->is_checked,
                'price' => (float) $item->price,
                'line_total' => round($item->qty * (float) $item->price, 2),
                'stock_quantity' => $product?->stock_quantity ?? 0,
            ];
        })->values();

        $sale = $cart->sale;
        $settings = $sale !== null && (! $sale->store_name || ! $sale->store_address)
            ? StoreSetting::current()
            : null;
        $saleItems = $sale?->items ?? collect();

        return [
            'token' => $cart->token,
            'cart_url' => route('shop.cart', $cart->token),
            'customer_name' => $cart->customer_name,
            'status' => $cart->status,
            'is_verified' => $cart->is_verified,
            'verification_code' => $cart->verification_code,
            'verification_url' => $cart->verification_code
                ? route('pos.verify', $cart->verification_code)
                : null,
            'service_rating' => $cart->service_rating,
            'service_feedback' => $cart->service_feedback,
            'rated_at' => $cart->rated_at?->toIso8601String(),
            'items' => $items,
            'total_pieces' => (int) $items->sum('qty'),
            'grand_total' => round((float) $items->sum('line_total'), 2),
            'receipt' => $sale ? [
                'id' => $sale->id,
                'paid' => $sale->status === 'completed',
                'created_at' => $sale->created_at?->format('M j, Y g:i A'),
                'store_name' => $sale->store_name ?: $settings?->store_name,
                'store_address' => $sale->store_address ?: $settings?->store_address,
                'payment_method' => $sale->payment_method,
                'payment_reference' => $sale->payment_reference,
                'subtotal' => (float) $sale->subtotal,
                'tax' => (float) $sale->tax,
                'discount' => (float) $sale->discount,
                'total' => (float) $sale->total,
                'items' => $saleItems->map(static fn ($item): array => [
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) ($item->promo_unit_price ?? $item->unit_price),
                    'is_promo' => (bool) $item->is_promo,
                    'line_total' => (float) $item->line_total,
                ])->values(),
            ] : null,
        ];
    }
}
