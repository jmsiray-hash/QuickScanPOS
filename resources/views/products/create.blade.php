@extends('layouts.app')
@section('title','Register Product | QuickScanPos')
@section('content')<h2>Register Product</h2><form class="form-grid" method="POST" action="{{ route('products.store') }}">@include('products._form',['submit'=>'Save Product','product'=>$product])</form>@endsection
