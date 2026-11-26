<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\CashierShift;
use App\Models\CashierCustomerScan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CashierDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $category = trim((string) ($validated['category'] ?? ''));

        $availableProducts = Product::query()
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0);

        $categories = (clone $availableProducts)
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $products = (clone $availableProducts)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%');
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        $cartQuantities = collect(session('pos_cart', []))
            ->mapWithKeys(static fn (array $item, string $productId): array => [$productId => (int) $item['quantity']])
            ->all();

        $activeShift = CashierShift::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();
        $scanReports = $this->scanReports($request->user()->id);
        $todayScanCount = CashierCustomerScan::query()
            ->where('cashier_id', $request->user()->id)
            ->whereDate('scanned_at', today())
            ->count();

        return view('cashier-dashboard.index', compact(
            'products',
            'categories',
            'search',
            'category',
            'cartQuantities',
            'activeShift',
            'scanReports',
            'todayScanCount',
        ));
    }

    public function scanReport(Request $request): JsonResponse
    {
        $cashierId = $request->user()->id;

        return response()->json([
            'scans' => $this->scanReports($cashierId),
            'today_count' => CashierCustomerScan::query()
                ->where('cashier_id', $cashierId)
                ->whereDate('scanned_at', today())
                ->count(),
        ]);
    }

    private function scanReports(int $cashierId): array
    {
        return CashierCustomerScan::query()
            ->with('customerCart.sale')
            ->where('cashier_id', $cashierId)
            ->latest('scanned_at')
            ->limit(20)
            ->get()
            ->map(static fn (CashierCustomerScan $scan): array => [
                'id' => $scan->id,
                'customer' => $scan->customer_name,
                'token' => $scan->cart_token,
                'item_count' => $scan->item_count,
                'scanned_at' => $scan->scanned_at->format('M j, g:i A'),
                'status' => strtoupper(str_replace('_', ' ', $scan->customerCart?->status ?? 'recorded')),
                'total' => $scan->customerCart?->sale?->total,
                'activity_url' => $scan->customer_cart_id !== null
                    ? route('customer-activity.show', $scan->customer_cart_id)
                    : null,
            ])
            ->all();
    }
}
