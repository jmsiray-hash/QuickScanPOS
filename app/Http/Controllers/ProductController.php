<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProductController extends Controller
{
    public function index(): View
    {
        $search = trim((string) request()->query('search', ''));
        $stockFilter = (string) request()->query('stock', 'all');
        if (! in_array($stockFilter, ['all', 'low', 'out'], true)) {
            $stockFilter = 'all';
        }

        $products = Product::query();
        if ($search !== '') {
            $products->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            });
        }
        if ($stockFilter === 'low') {
            $products->whereBetween('stock_quantity', [1, 5]);
        } elseif ($stockFilter === 'out') {
            $products->where('stock_quantity', 0);
        }

        return view('products.index', [
            'products' => $products->latest()->paginate(20)->withQueryString(),
            'search' => $search,
            'stockFilter' => $stockFilter,
            'activeProducts' => Product::where('is_active', true)->count(),
            'unitsInStock' => (int) Product::where('is_active', true)->sum('stock_quantity'),
            'lowStockProducts' => Product::where('is_active', true)->whereBetween('stock_quantity', [1, 5])->count(),
            'outOfStockProducts' => Product::where('is_active', true)->where('stock_quantity', 0)->count(),
        ]);
    }

    public function create(): View
    {
        return view('products.create', ['product' => new Product(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $this->validated($request);
        $image = $validated['image'] ?? null;
        unset($validated['image']);
        $imagePath = $image === null ? null : $this->storeProductImage($image);
        if ($imagePath !== null) {
            $validated['image_path'] = $imagePath;
        }

        try {
            $product = Product::create($validated);
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        if ($request->boolean('continue')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Product saved. Scan or enter the next item.',
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'barcode' => $product->barcode,
                    ],
                ], 201);
            }

            return to_route('products.create')->with('status', 'Product saved. Scan or enter the next item.');
        }

        return to_route('products.index')->with('status', 'Product registered successfully.');
    }

    public function edit(Product $product): View
    {
        return view('products.edit', compact('product'));
    }

    public function qr(Product $product): View
    {
        return view('products.qr', compact('product'));
    }

    public function image(Product $product): StreamedResponse
    {
        abort_unless(
            $product->image_path !== null && Storage::disk('public')->exists($product->image_path),
            404,
        );

        return Storage::disk('public')->response($product->image_path);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validated($request, $product);
        $image = $validated['image'] ?? null;
        $previousImagePath = $product->image_path;
        unset($validated['image']);
        $imagePath = $image === null ? null : $this->storeProductImage($image);
        if ($imagePath !== null) {
            $validated['image_path'] = $imagePath;
        }

        try {
            $saved = $product->update($validated);
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }
        if (! $saved) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw new RuntimeException('The product changes could not be saved.');
        }
        if ($imagePath !== null && $previousImagePath !== null) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return to_route('products.index')->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $imagePath = $product->image_path;

        DB::transaction(function () use ($product): void {
            $product->customerCartItems()->delete();
            $product->delete();
        });

        if ($imagePath !== null && ! Storage::disk('public')->delete($imagePath)) {
            report(new RuntimeException('Product was removed, but its stored image could not be deleted: '.$imagePath));
        }

        return to_route('products.index')->with(
            'status',
            'Product removed from inventory. Completed sales retain their saved item details.',
        );
    }

    public function lookup(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('barcode')));
        if ($code === '') {
            return response()->json(['message' => 'Product code is required.'], 422);
        }

        $product = Product::query()->whereRaw('UPPER(barcode) = ?', [$code])->first();

        return response()->json([
            'exists' => $product !== null,
            'product' => $product ? [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category' => $product->category,
                'selling_price' => $product->selling_price,
                'stock_quantity' => $product->stock_quantity,
                'is_active' => $product->is_active,
                'edit_url' => route('products.edit', $product),
            ] : null,
        ]);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $request->merge([
            'barcode' => strtoupper(trim((string) $request->input('barcode'))),
            'senior_pwd_eligible' => $request->boolean('senior_pwd_eligible'),
        ]);

        return $request->validate([
            'barcode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->ignore($product?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'senior_pwd_eligible' => ['required', 'boolean'],
        ]);
    }

    private function storeProductImage(UploadedFile $image): string
    {
        $path = $image->storePublicly('products', 'public');
        if ($path === false) {
            throw new RuntimeException('The product image could not be saved.');
        }

        return $path;
    }
}
