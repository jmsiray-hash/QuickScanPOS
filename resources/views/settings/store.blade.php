@extends('layouts.app')

@section('title', 'Company Settings | QuickScanPos')

@section('content')
    <section class="company-settings-page">
        <header class="settings-heading">
            <p class="settings-eyebrow">STORE PROFILE</p>
            <h1>Company &amp; receipt settings</h1>
            <p>Set receipt details and update the admin sign-in email or password.</p>
        </header>

        <div class="settings-layout">
            <form method="POST" action="{{ route('settings.store.update') }}" class="settings-form">
                @csrf
                @method('PUT')
                <div class="settings-form-heading">
                    <span aria-hidden="true">▤</span>
                    <div>
                        <h2>Company details</h2>
                        <p>Changes apply to sales completed after you save.</p>
                    </div>
                </div>
                <div class="field">
                    <label for="store-name">Company / store name</label>
                    <input id="store-name" name="store_name" value="{{ old('store_name', $settings->store_name) }}" maxlength="150" required>
                    @error('store_name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="store-address">Company address</label>
                    <textarea id="store-address" name="store_address" rows="4" maxlength="1000" required>{{ old('store_address', $settings->store_address) }}</textarea>
                    <span class="settings-field-hint">Use line breaks to separate street, barangay, city, and province.</span>
                    @error('store_address')<p class="error">{{ $message }}</p>@enderror
                </div>
                <section class="admin-email-section" aria-labelledby="admin-email-heading">
                    <div class="admin-email-heading">
                        <span aria-hidden="true">@</span>
                        <div>
                            <h3 id="admin-email-heading">Admin sign-in email</h3>
                            <p>These credentials belong to your current administrator account. Changing them does not change its role.</p>
                        </div>
                    </div>
                    <div class="field">
                        <label for="admin-email">Admin Gmail / email address</label>
                        <input id="admin-email" name="admin_email" type="email" value="{{ old('admin_email', $adminEmail) }}" maxlength="254" autocomplete="email" data-original-email="{{ strtolower($adminEmail) }}" required>
                        @error('admin_email')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="current-password">Current password <span class="settings-conditional-label">(required to change sign-in details)</span></label>
                        <input id="current-password" name="current_password" type="password" autocomplete="current-password">
                        <span class="settings-field-hint">Required to change the admin email or password.</span>
                        @error('current_password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="new-password">New admin password</label>
                        <input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="12">
                        <span class="settings-field-hint">Leave blank to keep the current password. Use at least 12 characters.</span>
                        @error('new_password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="new-password-confirmation">Confirm new admin password</label>
                        <input id="new-password-confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" minlength="12">
                    </div>
                    <p class="settings-field-hint">Changing the email sends a verification link to the new address. You can also create another administrator from Users.</p>
                </section>
                <button class="button settings-save" type="submit">Save settings</button>
            </form>

            <aside class="receipt-preview-panel" aria-label="Receipt details preview">
                <div class="receipt-preview-heading">
                    <div>
                        <p class="settings-eyebrow">CUSTOMER RECEIPT</p>
                        <h2>Receipt preview</h2>
                    </div>
                    <span aria-hidden="true">↗</span>
                </div>
                <div class="receipt-preview-paper">
                    <header>
                        <strong>{{ $settings->store_name }}</strong>
                        <p>{{ $settings->store_address }}</p>
                        <span>SALES RECEIPT</span>
                    </header>
                    <div class="receipt-preview-meta">
                        <div><span>Receipt</span><strong># —</strong></div>
                        <div><span>Date &amp; time</span><strong>Shown at checkout</strong></div>
                        <div><span>Cashier / customer</span><strong>Shown at checkout</strong></div>
                    </div>
                    <div class="receipt-preview-items">
                        <div><span>Item · quantity · unit price</span><strong>Sale items appear here</strong></div>
                        <div><span>Subtotal, tax &amp; discount</span><strong>Calculated per sale</strong></div>
                        <div class="receipt-preview-total"><span>Total paid</span><strong>Payment &amp; change shown</strong></div>
                    </div>
                    <p class="receipt-preview-thanks">Thank you for shopping with us.</p>
                </div>
                <p class="receipt-preview-note">Your saved company details are included on printed receipts and customer checkout receipts. Past sales keep the business details saved when they were completed.</p>
            </aside>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const emailInput = document.getElementById('admin-email');
            const passwordInput = document.getElementById('current-password');
            const newPasswordInput = document.getElementById('new-password');
            const originalEmail = emailInput.dataset.originalEmail;
            const updatePasswordRequirement = () => {
                passwordInput.required = emailInput.value.trim().toLowerCase() !== originalEmail || newPasswordInput.value.length > 0;
            };
            emailInput.addEventListener('input', updatePasswordRequirement);
            newPasswordInput.addEventListener('input', updatePasswordRequirement);
            updatePasswordRequirement();
        });
    </script>

    <style>
        .company-settings-page { margin:0 auto; max-width:1100px; }
        .settings-heading { margin-bottom:22px; }
        .settings-eyebrow { color:var(--brand); font-size:.7rem; font-weight:800; letter-spacing:.14em; margin:0 0 5px; }
        .settings-heading h1 { font-size:clamp(1.7rem,3vw,2.25rem); margin:0 0 6px; }
        .settings-heading>p:last-child { color:var(--muted); margin:0; }
        .settings-layout { align-items:start; display:grid; gap:20px; grid-template-columns:minmax(0,1.05fr) minmax(300px,.95fr); }
        .settings-form,.receipt-preview-panel { background:#fff; border:1px solid var(--line); border-radius:12px; box-shadow:0 8px 24px rgba(24,56,48,.06); padding:22px; }
        .settings-form { display:grid; gap:16px; max-width:none; }
        .settings-form-heading { align-items:center; border-bottom:1px solid #e8efed; display:flex; gap:12px; margin-bottom:1px; padding-bottom:14px; }
        .settings-form-heading>span { align-items:center; background:#e4f6ef; border-radius:10px; color:#0f766e; display:flex; font-size:1.2rem; height:40px; justify-content:center; width:40px; }
        .settings-form-heading h2,.receipt-preview-heading h2 { font-size:1.05rem; margin:0; }
        .settings-form-heading p { color:var(--muted); font-size:.82rem; margin:3px 0 0; }
        .settings-form .field { gap:7px; }
        .settings-form .field label { color:#334b47; font-size:.86rem; }
        .settings-form input,.settings-form textarea { background:#fbfdfc; border:1px solid #cbd9d5; border-radius:7px; color:var(--ink); font:inherit; padding:10px 12px; width:100%; }
        .settings-form input:focus,.settings-form textarea:focus { border-color:#159e8e; box-shadow:0 0 0 3px rgba(21,158,142,.12); outline:0; }
        .settings-form textarea { min-height:110px; resize:vertical; }
        .settings-field-hint { color:var(--muted); font-size:.74rem; }
        .settings-save { justify-self:start; min-height:42px; padding:10px 18px; }
        .admin-email-section { border-top:1px solid #e8efed; display:grid; gap:12px; margin-top:1px; padding-top:16px; }
        .admin-email-heading { align-items:flex-start; display:flex; gap:10px; }
        .admin-email-heading>span { align-items:center; background:#edf2ff; border-radius:8px; color:#485c95; display:flex; flex:none; font-size:1.05rem; height:34px; justify-content:center; width:34px; }
        .admin-email-heading h3 { color:#334b47; font-size:.9rem; margin:0; }
        .admin-email-heading p { color:var(--muted); font-size:.75rem; line-height:1.4; margin:3px 0 0; }
        .settings-conditional-label { color:var(--muted); font-size:.72rem; font-weight:400; }
        .receipt-preview-panel { background:linear-gradient(155deg,#f8fcfa,#edf7f3); }
        .receipt-preview-heading { align-items:center; display:flex; justify-content:space-between; margin-bottom:14px; }
        .receipt-preview-heading .settings-eyebrow { font-size:.6rem; }
        .receipt-preview-heading>span { align-items:center; background:#e2f2eb; border-radius:9px; color:#14745f; display:flex; font-size:1.2rem; height:36px; justify-content:center; width:36px; }
        .receipt-preview-paper { background:#fff; border:1px dashed #bbd0c8; border-radius:8px; box-shadow:0 5px 16px rgba(21,62,49,.06); margin:0 auto; max-width:390px; padding:20px; }
        .receipt-preview-paper>header { border-bottom:1px dashed #cbd8d4; padding-bottom:12px; text-align:center; }
        .receipt-preview-paper>header strong { color:#183e36; display:block; font-size:1.05rem; overflow-wrap:anywhere; }
        .receipt-preview-paper>header p { color:#63766f; font-size:.78rem; margin:4px 0 10px; white-space:pre-line; }
        .receipt-preview-paper>header span { color:#50685f; font-size:.66rem; font-weight:800; letter-spacing:.14em; }
        .receipt-preview-meta,.receipt-preview-items { display:grid; gap:8px; padding:12px 0; }
        .receipt-preview-meta { border-bottom:1px dashed #cbd8d4; }
        .receipt-preview-meta>div,.receipt-preview-items>div { display:flex; flex-direction:column; gap:1px; }
        .receipt-preview-meta span,.receipt-preview-items span { color:#71817b; font-size:.68rem; }
        .receipt-preview-meta strong,.receipt-preview-items strong { color:#354b44; font-size:.75rem; }
        .receipt-preview-total { border-top:1px dashed #cbd8d4; margin-top:3px; padding-top:9px; }
        .receipt-preview-total strong { color:#0f766e; }
        .receipt-preview-thanks { color:#687b74; font-size:.72rem; margin:0; padding-top:8px; text-align:center; }
        .receipt-preview-note { color:#64786f; font-size:.77rem; line-height:1.5; margin:13px 0 0; }
        @media(max-width:760px) { .settings-layout { grid-template-columns:1fr; } }
        @media(max-width:520px) { .settings-form,.receipt-preview-panel { padding:16px; } }
    </style>
@endsection
