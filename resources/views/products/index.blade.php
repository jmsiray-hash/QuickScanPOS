@extends('layouts.app')
@section('title','Products | QuickScanPos')
@section('content')
<div class="page-actions"><h2>Inventory</h2><a class="button" href="{{ route('products.create') }}">Register Product</a></div>
<section class="inventory-summary" aria-label="Inventory summary">
    <div><span>Active products</span><strong>{{ number_format($activeProducts) }}</strong></div>
    <div><span>Units in stock</span><strong>{{ number_format($unitsInStock) }}</strong></div>
    <div><span>Low stock (1-5)</span><strong>{{ number_format($lowStockProducts) }}</strong></div>
    <div><span>Out of stock</span><strong>{{ number_format($outOfStockProducts) }}</strong></div>
</section>
<form method="GET" action="{{ route('products.index') }}" class="inventory-filters">
    <label for="inventory-search">Find product</label>
    <input id="inventory-search" name="search" value="{{ $search }}" placeholder="Name, category, or barcode">
    <label for="stock-filter">Stock</label>
    <select id="stock-filter" name="stock">
        <option value="all" @selected($stockFilter === 'all')>All stock</option>
        <option value="low" @selected($stockFilter === 'low')>Low stock (1-5)</option>
        <option value="out" @selected($stockFilter === 'out')>Out of stock</option>
    </select>
    <button class="button" type="submit">Filter</button>
    @if($search !== '' || $stockFilter !== 'all')<a href="{{ route('products.index') }}">Clear</a>@endif
</form>
<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Barcode / Product Code</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead><tbody>@forelse($products as $p)<tr><td>@if($p->image_path)<img class="inventory-product-image" src="{{ route('products.image', $p) }}" alt="{{ $p->name }}" onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('span'),{textContent:'No photo'}));">@else<span>No photo</span>@endif</td><td>{{ $p->barcode }}</td><td>{{ $p->name }}</td><td>{{ $p->category ?: '—' }}</td><td>₱{{ number_format($p->selling_price,2) }}</td><td><strong class="{{ $p->stock_quantity === 0 ? 'stock-out' : ($p->stock_quantity <= 5 ? 'stock-low' : '') }}">{{ $p->stock_quantity }}</strong></td><td>{{ $p->is_active?'Active':'Inactive' }}</td><td><div class="row-actions product-actions"><a href="{{ route('products.qr',$p) }}">Print QR</a><a href="{{ route('products.edit',$p) }}">Edit / Restock</a><form method="POST" action="{{ route('products.destroy', $p) }}" onsubmit="return confirm('Remove this product from inventory? It will also be removed from open carts, while completed sales keep their saved item details.')">@csrf @method('DELETE')<button class="remove-product-button" type="submit">Remove</button></form></div></td></tr>@empty<tr><td colspan="8">No matching products.</td></tr>@endforelse</tbody></table></div>{{ $products->links() }}
<style>
    .inventory-summary { display:grid; gap:12px; grid-template-columns:repeat(4,minmax(0,1fr)); margin:20px 0; }
    .inventory-summary div { background:#f0fdfa; border:1px solid #ccfbf1; border-radius:8px; display:grid; gap:4px; padding:16px; }
    .inventory-summary span { color:var(--muted); font-size:.88rem; }
    .inventory-summary strong { color:var(--brand-dark); font-size:1.5rem; }
    .inventory-filters { align-items:end; display:flex; flex-wrap:wrap; gap:10px; margin:20px 0; }
    .inventory-filters label { font-weight:700; }
    .inventory-filters input,.inventory-filters select { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:9px; }
    .stock-low { color:#b45309; } .stock-out { color:#b91c1c; }
    .inventory-product-image { border-radius:6px; height:48px; object-fit:cover; width:48px; }
    .product-actions { flex-wrap:wrap; }
    .remove-product-button { background:transparent; border:0; color:#b91c1c; cursor:pointer; font:inherit; padding:0; text-decoration:underline; }
    @media(max-width:700px) { .inventory-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>
@endsection
