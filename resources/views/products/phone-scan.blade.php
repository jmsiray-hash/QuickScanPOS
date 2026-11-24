@extends('layouts.app')

@section('title', 'Phone Product Scanner | QuickScanPos')

@section('content')
    <section class="phone-scan-page">
        <h2>Phone Product Scanner</h2>
        <p>Scan a product barcode, use OCR to read a printed code, or enter the code manually. It will be sent to the cashier's product registration form.</p>
        <div id="phone-scan-message" class="alert" role="status" hidden></div>
        @include('components.code-scanner')
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const message = document.getElementById('phone-scan-message');
            let submitted = false;

            document.addEventListener('pos:code-scanned', async event => {
                if (submitted) return;
                submitted = true;
                try {
                    const response = await fetch(@json($submitUrl), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ code: event.detail.code }),
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'The product code could not be sent.');
                    message.textContent = result.message + ' You can return to the desktop registration form.';
                    message.className = 'alert alert-success';
                    message.hidden = false;
                } catch (error) {
                    submitted = false;
                    message.textContent = error.message || 'The product code could not be sent.';
                    message.className = 'alert alert-errors';
                    message.hidden = false;
                }
            });

            document.querySelector('.scanner-input').focus();
        });
    </script>
    <style>
        .phone-scan-page { max-width:760px; margin:auto; }
        @media(max-width:640px) { main { padding:16px; } }
    </style>
@endsection
