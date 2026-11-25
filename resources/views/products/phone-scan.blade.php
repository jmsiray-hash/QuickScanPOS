@extends('layouts.app')

@section('title', 'Phone Product Scanner | QuickScanPos')

@section('content')
    <section class="phone-scan-page">
        <h2>Phone Product Scanner</h2>
        <p>Capture a photo, scan or enter the product code, fill in the details, then save the product directly to store inventory.</p>
        <div id="phone-scan-message" class="alert" role="status" hidden></div>
        <section class="phone-product-photo">
            <label for="phone-product-photo-input">Take a photo of this product</label>
            <input id="phone-product-photo-input" type="file" accept="image/*" capture="environment">
            <img id="phone-product-photo-preview" alt="Captured product photo preview" hidden>
            <button id="phone-product-photo-clear" class="button button-secondary" type="button" hidden>Remove photo</button>
            <p>Take a photo, then scan the matching item code. Both will be saved together.</p>
        </section>
        @include('components.code-scanner')
        <form id="mobile-product-form" class="mobile-product-form">
            <h3>Product details</h3>
            <div class="field"><label for="mobile-product-barcode">Barcode / product code</label><input id="mobile-product-barcode" name="barcode" maxlength="100" required></div>
            <div class="field"><label for="mobile-product-name">Product name</label><input id="mobile-product-name" name="name" maxlength="255" required></div>
            <div class="field"><label for="mobile-product-category">Category</label><input id="mobile-product-category" name="category" maxlength="100"></div>
            <div class="mobile-product-prices">
                <div class="field"><label for="mobile-product-cost">Cost price</label><input id="mobile-product-cost" name="cost_price" type="number" min="0" step="0.01" required></div>
                <div class="field"><label for="mobile-product-price">Selling price</label><input id="mobile-product-price" name="selling_price" type="number" min="0" step="0.01" required></div>
            </div>
            <div class="field"><label for="mobile-product-stock">Starting stock quantity</label><input id="mobile-product-stock" name="stock_quantity" type="number" min="0" step="1" value="0" required></div>
            <button id="save-mobile-product" class="button" type="submit">Save Product to Inventory</button>
            <button id="send-mobile-code" class="button button-secondary" type="button">Send Code to Cashier Desktop Instead</button>
        </form>
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
            const productForm = document.getElementById('mobile-product-form');
            const barcodeInput = document.getElementById('mobile-product-barcode');
            const saveButton = document.getElementById('save-mobile-product');

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

            productForm.addEventListener('submit', async event => {
                event.preventDefault();
                if (submitting || photoProcessing) return;
                if (!currentPhotoData) {
                    message.textContent = 'Take a product photo before saving.';
                    message.className = 'alert alert-errors';
                    message.hidden = false;
                    return;
                }

                submitting = true;
                saveButton.disabled = true;
                message.textContent = 'Saving product and photo to inventory...';
                message.className = 'alert';
                message.hidden = false;
                try {
                    const formData = new FormData(productForm);
                    const response = await fetch(@json($saveUrl), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            barcode: formData.get('barcode'),
                            name: formData.get('name'),
                            category: formData.get('category'),
                            cost_price: formData.get('cost_price'),
                            selling_price: formData.get('selling_price'),
                            stock_quantity: formData.get('stock_quantity'),
                            photo: currentPhotoData,
                        }),
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        const firstError = result.errors ? Object.values(result.errors).flat()[0] : null;
                        throw new Error(firstError || result.message || 'Product could not be saved.');
                    }

                    productForm.reset();
                    clearPhoto();
                    message.textContent = result.message + ' ' + result.product.name + ' · ₱'
                        + Number(result.product.selling_price).toFixed(2) + ' · Stock ' + result.product.stock_quantity + '. Ready for the next product.';
                    message.className = 'alert alert-success';
                } catch (error) {
                    message.textContent = error.message || 'Product could not be saved.';
                    message.className = 'alert alert-errors';
                } finally {
                    submitting = false;
                    saveButton.disabled = false;
                }
            });

            document.getElementById('send-mobile-code').addEventListener('click', () => {
                if (photoProcessing) return;
                const code = barcodeInput.value.trim();
                if (!code) {
                    message.textContent = 'Scan or enter a product code first.';
                    message.className = 'alert alert-errors';
                    message.hidden = false;
                    return;
                }
                pendingCodes.push({ code, photo: currentPhotoData });
                barcodeInput.value = '';
                clearPhoto();
                sendNextCode();
            });

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
                barcodeInput.value = event.detail.code;
                document.getElementById('mobile-product-name').focus();
                message.textContent = currentPhotoData
                    ? 'Code scanned and photo ready. Enter the item details, then save to inventory.'
                    : 'Code scanned. Take a product photo, enter the item details, then save to inventory.';
                message.className = 'alert alert-success';
                message.hidden = false;
            });

            document.querySelector('.scanner-input').focus();
        });
    </script>
    <style>
        .phone-scan-page { max-width:760px; margin:auto; }
        .phone-product-photo { border:1px solid #dbe5e3; border-radius:8px; display:grid; gap:8px; margin:16px 0; padding:14px; }
        .phone-product-photo label { font-weight:700; }
        #phone-product-photo-preview { border-radius:8px; max-height:240px; max-width:100%; object-fit:contain; }
        .mobile-product-form { border:1px solid #dbe5e3; border-radius:8px; display:grid; gap:14px; margin-top:20px; padding:16px; }
        .mobile-product-form h3 { margin:0; }
        .mobile-product-form .field { display:grid; gap:6px; }
        .mobile-product-form input { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:10px; width:100%; }
        .mobile-product-prices { display:grid; gap:12px; grid-template-columns:repeat(2,minmax(0,1fr)); }
        #save-mobile-product { min-height:48px; }
        @media(max-width:640px) { main { padding:16px; } }
    </style>
@endsection
