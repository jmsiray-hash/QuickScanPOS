@extends('layouts.app')

@section('title', 'Edit User | QuickScanPos')

@section('content')
    <h2>Edit User</h2>
    <form class="form-grid" action="{{ route('users.update', $user) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required maxlength="50">
            @error('username')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="full_name">Full Name</label>
            <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $user->full_name) }}" required maxlength="100">
            @error('full_name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        @if (auth()->user()->isAdmin())
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="cashier" @selected(old('role', $user->role) === 'cashier')>Cashier</option>
                    <option value="manager" @selected(old('role', $user->role) === 'manager')>Manager</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                </select>
                @error('role')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Set a new password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12">
                <p class="field-hint">Leave blank to keep the current password. Use at least 12 characters to reset this account.</p>
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12">
            </div>
            @if (auth()->id() === $user->id)
                <div class="field">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                    @error('current_password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const newPassword = document.getElementById('password');
                        document.getElementById('current_password').required = newPassword.value.length > 0;
                        newPassword.addEventListener('input', () => {
                            document.getElementById('current_password').required = newPassword.value.length > 0;
                        });
                    });
                </script>
            @endif
        @endif
        <div class="field">
            <label for="avatar">Avatar</label>
            <img
                class="avatar avatar-preview"
                src="{{ $user->avatar ? route('users.avatar', $user) : asset('images/avatar-placeholder.svg') }}"
                alt="{{ $user->full_name }} avatar"
                width="120"
                height="120"
            >
            <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.avif,image/jpeg,image/png,image/gif,image/webp,image/bmp,image/avif">
            <p class="field-hint">Optional. JPEG, PNG, GIF, WebP, BMP, or AVIF. Maximum file size: 2 MB.</p>
            @error('avatar')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="row-actions">
            <button class="button" type="submit">Update User</button>
            <a class="button button-secondary" href="{{ route('users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
