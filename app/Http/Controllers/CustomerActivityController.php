<?php

namespace App\Http\Controllers;

use App\Models\CustomerCart;
use App\Services\CustomerCartPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerActivityController extends Controller
{
    public function ratings(Request $request): View|JsonResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'rating_count' => (int) $summary->rating_count,
                'average_rating' => $summary->average_rating === null
                    ? null
                    : (float) $summary->average_rating,
                'ratings' => $ratings->getCollection()->map(static fn (CustomerCart $cart): array => [
                    'rating' => (int) $cart->service_rating,
                    'customer_name' => $cart->customer_name,
                    'items_count' => (int) $cart->items_count,
                    'feedback' => $cart->service_feedback,
                    'sale_id' => $cart->sale_id,
                    'submitted_at' => $cart->rated_at->format('M j, Y g:i A'),
                ])->values(),
            ]);
        }

        return view('customers.ratings', compact('ratings', 'summary'));
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:live,history'],
        ]);
        $activityView = $validated['view'] ?? 'live';

        $carts = CustomerCart::query()
            ->when($activityView === 'history', function ($query): void {
                $query->where(function ($query): void {
                    $query->whereIn('status', ['paid', 'expired'])
                        ->orWhereNotNull('service_rating')
                        ->orWhere(function ($query): void {
                            $query->whereNotNull('expires_at')
                                ->where('expires_at', '<=', now());
                        });
                });
            }, function ($query): void {
                $query->whereIn('status', ['shopping', 'ready_for_checkout', 'verified_ready'])
                    ->whereNull('service_rating')
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->withSum('items', 'qty')
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('customers.activity', compact('carts', 'activityView'));
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
