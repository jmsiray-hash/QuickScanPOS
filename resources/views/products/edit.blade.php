@extends('layouts.app')
@section('title','Edit Product | QuickScanPos')
@section('content')
    <div class="page-actions">
        <h2>Edit Product</h2>
        <a class="button button-secondary" href="{{ route('products.qr', $product) }}">Print Product QR</a>
    </div>
    <form class="form-grid" method="POST" action="{{ route('products.update', $product) }}">
        @method('PUT')
        @include('products._form', ['submit' => 'Update Product', 'product' => $product])
    </form>
@endsection
