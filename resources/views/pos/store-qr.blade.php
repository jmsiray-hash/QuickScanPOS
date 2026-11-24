@extends('layouts.app')

@section('title', 'Store Scan & Go QR | QuickScanPos')

@section('content')
    <section class="store-qr">
        <h2>Scan &amp; Go Store Entry</h2>
        <p>Print or display this QR code so customers can start a shopping cart on their phones.</p>
        <div class="store-url-settings">
            <label for="store-entry-url">Phone-accessible store URL</label>
            <input id="store-entry-url" type="url" value="{{ $shopUrl }}" autocomplete="url">
            <p id="store-entry-warning" class="field-hint" role="status"></p>
        </div>
        <div id="store-entry-qr" aria-label="Store entry QR code"></div>
        <p><a id="store-entry-link" href="{{ $shopUrl }}">{{ $shopUrl }}</a></p>
        <button class="button" type="button" onclick="window.print()">Print QR</button>
    </section>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById('store-entry-url');
            const qr = document.getElementById('store-entry-qr');
            const link = document.getElementById('store-entry-link');
            const warning = document.getElementById('store-entry-warning');

            function renderQr() {
                const url = input.value.trim();
                qr.replaceChildren();
                link.href = url || '#';
                link.textContent = url;
                warning.textContent = '';

                if (!url) {
                    warning.textContent = 'Enter a store URL that your phone can reach.';
                    return;
                }

                try {
                    const destination = new URL(url);
                    if (!['http:', 'https:'].includes(destination.protocol)) {
                        throw new Error('Use an HTTP or HTTPS URL.');
                    }
                    if (['localhost', '127.0.0.1', '::1'].includes(destination.hostname)) {
                        warning.textContent = 'This address is only reachable on this computer. Paste the VS Code HTTPS forwarded URL here for phone scanning.';
                    } else if (destination.protocol !== 'https:') {
                        warning.textContent = 'Phone camera access requires HTTPS. Use the VS Code HTTPS forwarded URL.';
                    }
                } catch (error) {
                    warning.textContent = error.message || 'Enter a valid phone-accessible URL.';
                    return;
                }

                if (window.QRCode) {
                    new QRCode(qr, { text: url, width: 260, height: 260 });
                } else {
                    warning.textContent = 'The QR library did not load. Check the phone-accessible URL and internet connection.';
                }
            }

            input.addEventListener('input', renderQr);
            renderQr();
        });
    </script>
    <style>
        .store-qr { max-width:650px; margin:auto; text-align:center; }
        .store-url-settings { margin:20px auto; max-width:560px; text-align:left; }
        .store-url-settings label { display:block; font-weight:700; margin-bottom:6px; }
        .store-url-settings input { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:10px; width:100%; }
        #store-entry-warning { color:#b45309; min-height:1.5em; }
        #store-entry-qr { display:flex; justify-content:center; margin:24px; }
        @media print { header, footer, button, .store-url-settings { display:none !important; } main { border:0; } }
    </style>
@endsection
