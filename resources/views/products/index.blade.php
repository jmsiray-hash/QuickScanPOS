@extends('layouts.app')
@section('title','Products | QuickScanPos')
@section('content')
<div class="page-actions"><h2>Products</h2><a class="button" href="{{ route('products.create') }}">Register Product</a></div>
<div class="table-wrap"><table><thead><tr><th>Barcode / Product Code</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead><tbody>@forelse($products as $p)<tr><td>{{ $p->barcode }}</td><td>{{ $p->name }}</td><td>{{ $p->category ?: '—' }}</td><td>₱{{ number_format($p->selling_price,2) }}</td><td>{{ $p->stock_quantity }}</td><td>{{ $p->is_active?'Active':'Inactive' }}</td><td><div class="row-actions"><a href="{{ route('products.qr',$p) }}">Print QR</a><a href="{{ route('products.edit',$p) }}">Edit / Restock</a></div></td></tr>@empty<tr><td colspan="7">No products registered.</td></tr>@endforelse</tbody></table></div>{{ $products->links() }}
@endsection
