@extends('layouts.app')

@section('title', 'Product QR Code | POS Workspace')

@section('content')
    <section class="product-qr">
        <h2>Product QR Code</h2>
        <h3>{{ $product->name }}</h3>
        <p>Barcode / Printed Product Code: <code>{{ $product->barcode }}</code></p>
        <div id="product-qr-code" aria-label="Product QR code"></div>
        <p>Scan this QR with the cashier POS camera or a Scan &amp; Go phone. It encodes the product code.</p>
        <div class="row-actions qr-actions">
            <button class="button" type="button" onclick="window.print()">Print QR</button>
            <a class="button button-secondary" href="{{ route('products.index') }}">Back to Products</a>
        </div>
        <p id="qr-fallback" hidden>QR generation is unavailable. Use this product code: <strong>{{ $product->barcode }}</strong></p>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const target = document.getElementById('product-qr-code');
            if (window.QRCode) {
                new QRCode(target, {
                    text: @json($product->barcode),
                    width: 280,
                    height: 280,
                });
            } else {
                document.getElementById('qr-fallback').hidden = false;
            }
        });
    </script>
    <style>
        .product-qr { max-width:620px; margin:auto; text-align:center; }
        #product-qr-code { display:flex; justify-content:center; margin:24px; }
        .qr-actions { justify-content:center; }
        @media print {
            header, footer, .qr-actions, nav { display:none !important; }
            main { border:0; }
        }
        @media(max-width:640px) { main { padding:18px; } .qr-actions { flex-direction:column; } }
    </style>
@endsection
