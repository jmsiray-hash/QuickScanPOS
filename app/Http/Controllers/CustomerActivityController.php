<?php

namespace App\Http\Controllers;

use App\Models\CustomerCart;
use App\Services\CustomerCartPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CustomerActivityController extends Controller
{
    public function index(): View
    {
        $carts = CustomerCart::query()
            ->whereIn('status', ['shopping', 'ready_for_checkout', 'verified_ready', 'paid'])
            ->where(function ($query): void {
                $query->where('status', 'paid')
                    ->orWhereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->withCount('items')
            ->latest('updated_at')
            ->paginate(30);

        return view('customers.activity', compact('carts'));
    }

    public function show(CustomerCart $customerCart): View
    {
        $customerCart->load(['items.product', 'sale.items']);

        return view('customers.activity-show', [
            'cart' => $customerCart,
            'cartData' => CustomerCartPresenter::make($customerCart),
        ]);
    }

    public function status(CustomerCart $customerCart): JsonResponse
    {
        return response()->json([
            'cart' => CustomerCartPresenter::make($customerCart->fresh()),
        ]);
    }
}
