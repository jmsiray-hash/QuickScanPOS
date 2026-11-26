<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
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

        return view('cashier-dashboard.index', compact('products', 'categories', 'search', 'category', 'cartQuantities'));
    }
}
