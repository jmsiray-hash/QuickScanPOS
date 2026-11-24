<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', ['products' => Product::latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('products.create', ['product' => new Product(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request));

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

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return to_route('products.index')->with('status', 'Product updated successfully.');
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
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
