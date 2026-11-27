<?php

namespace App\Http\Controllers;

use App\Models\CustomerCart;
use App\Services\CustomerCartPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CustomerActivityController extends Controller
{
    public function ratings(): View
    {
        $ratedCarts = CustomerCart::query()
            ->whereNotNull('service_rating')
            ->whereNotNull('rated_at');

        $summary = (clone $ratedCarts)
            ->selectRaw('COUNT(*) as rating_count, AVG(service_rating) as average_rating')
            ->first();

        $ratings = $ratedCarts
            ->with('sale')
            ->withCount('items')
            ->latest('rated_at')
            ->paginate(30);

        return view('customers.ratings', compact('ratings', 'summary'));
    }

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
