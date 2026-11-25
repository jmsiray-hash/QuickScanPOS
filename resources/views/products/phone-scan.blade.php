@extends('layouts.app')

@section('title', 'Phone Product Scanner | QuickScanPos')

@section('content')
    <section class="phone-scan-page">
        <h2>Phone Product Scanner</h2>
        <p>Scan product barcodes one after another, use OCR to read a printed code, or enter codes manually. Each code is sent to the cashier's registration form.</p>
        <div id="phone-scan-message" class="alert" role="status" hidden></div>
        <section class="phone-product-photo">
            <label for="phone-product-photo-input">Take a photo of this product</label>
            <input id="phone-product-photo-input" type="file" accept="image/*" capture="environment">
            <img id="phone-product-photo-preview" alt="Captured product photo preview" hidden>
            <button id="phone-product-photo-clear" class="button button-secondary" type="button" hidden>Remove photo</button>
            <p>Take the photo first, then scan the item's barcode. The photo will be attached to that product.</p>
        </section>
        @include('components.code-scanner')
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const message = document.getElementById('phone-scan-message');
            let submitting = false;
            let photoProcessing = false;
            let currentPhotoData = null;
            const pendingCodes = [];
            const photoInput = document.getElementById('phone-product-photo-input');
            const photoPreview = document.getElementById('phone-product-photo-preview');
            const clearPhotoButton = document.getElementById('phone-product-photo-clear');

            function readAsDataUrl(blob) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(new Error('The product photo could not be read.'));
                    reader.readAsDataURL(blob);
                });
            }

            async function compressPhoto(file) {
                const url = URL.createObjectURL(file);
                try {
                    const image = new Image();
                    image.src = url;
                    await image.decode();

                    for (const [maxSize, quality] of [[1280, 0.78], [1024, 0.72], [800, 0.65]]) {
                        const scale = Math.min(1, maxSize / Math.max(image.naturalWidth, image.naturalHeight));
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.round(image.naturalWidth * scale);
                        canvas.height = Math.round(image.naturalHeight * scale);
                        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
                        if (blob && blob.size <= 2 * 1024 * 1024) return await readAsDataUrl(blob);
                    }
                    throw new Error('Photo is too large to send. Retake it closer to the item.');
                } finally {
                    URL.revokeObjectURL(url);
                }
            }

            photoInput.addEventListener('change', async () => {
                const file = photoInput.files?.[0];
                if (!file) return;
                photoProcessing = true;
                message.textContent = 'Preparing product photo...';
                message.className = 'alert';
                message.hidden = false;
                try {
                    currentPhotoData = await compressPhoto(file);
                    photoPreview.src = currentPhotoData;
                    photoPreview.hidden = false;
                    clearPhotoButton.hidden = false;
                    message.textContent = 'Photo ready. Scan this product code to send both to the cashier.';
                } catch (error) {
                    currentPhotoData = null;
                    photoInput.value = '';
                    message.textContent = error.message || 'The product photo could not be prepared.';
                    message.className = 'alert alert-errors';
                } finally {
                    photoProcessing = false;
                }
            });

            function clearPhoto() {
                currentPhotoData = null;
                photoInput.value = '';
                photoPreview.removeAttribute('src');
                photoPreview.hidden = true;
                clearPhotoButton.hidden = true;
            }

            clearPhotoButton.addEventListener('click', clearPhoto);

            async function sendNextCode() {
                if (submitting || pendingCodes.length === 0) return;
                submitting = true;
                const scan = pendingCodes.shift();
                try {
                    const response = await fetch(@json($submitUrl), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ code: scan.code, photo: scan.photo }),
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'The product code could not be sent.');
                    message.textContent = result.message;
                    message.className = 'alert alert-success';
                    message.hidden = false;
                } catch (error) {
                    pendingCodes.unshift(scan);
                    message.textContent = error.message || 'The product code could not be sent.';
                    message.className = 'alert alert-errors';
                    message.hidden = false;
                    return;
                } finally {
                    submitting = false;
                }
                if (pendingCodes.length > 0) sendNextCode();
            }

            document.addEventListener('pos:code-scanned', event => {
                if (photoProcessing) {
                    message.textContent = 'Wait for the product photo to finish preparing, then scan again.';
                    message.className = 'alert alert-errors';
                    message.hidden = false;
                    return;
                }
                pendingCodes.push({ code: event.detail.code, photo: currentPhotoData });
                clearPhoto();
                sendNextCode();
            });

            document.querySelector('.scanner-input').focus();
        });
    </script>
    <style>
        .phone-scan-page { max-width:760px; margin:auto; }
        .phone-product-photo { border:1px solid #dbe5e3; border-radius:8px; display:grid; gap:8px; margin:16px 0; padding:14px; }
        .phone-product-photo label { font-weight:700; }
        #phone-product-photo-preview { border-radius:8px; max-height:240px; max-width:100%; object-fit:contain; }
        @media(max-width:640px) { main { padding:16px; } }
    </style>
@endsection
