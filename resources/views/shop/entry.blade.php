@extends('layouts.app')

@section('title', 'Start Shopping | POS Workspace')

@section('content')
    <section class="shop-panel">
        <h2>Scan &amp; Go</h2>
        <p>Enter your name to start a shopping cart. Scan product barcodes or printed product codes as you shop.</p>
        <form class="form-grid" action="{{ route('shop.start') }}" method="POST">
            @csrf
            <div class="field">
                <label for="customer_name">Your name</label>
                <input id="customer_name" name="customer_name" maxlength="100" required autocomplete="name" autofocus>
                @error('customer_name')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>
            <button class="button" type="submit">Start Shopping</button>
        </form>
    </section>
    <style>
        .shop-panel { margin:auto; max-width:620px; }
        @media(max-width:640px) { main { padding:20px; } .shop-panel .button { width:100%; } }
    </style>
@endsection
