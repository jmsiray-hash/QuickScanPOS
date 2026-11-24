@extends('layouts.app')

@section('title', 'Create User | POS Workspace')

@section('content')
    <h2>Create User</h2>
    <form class="form-grid" action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" required maxlength="50">
            @error('username')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="full_name">Full Name</label>
            <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required maxlength="100">
            @error('full_name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required minlength="8">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8">
        </div>
        <div class="field">
            <label for="avatar">Avatar</label>
            <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.avif,image/jpeg,image/png,image/gif,image/webp,image/bmp,image/avif">
            <p class="field-hint">Optional. JPEG, PNG, GIF, WebP, BMP, or AVIF. Maximum file size: 2 MB.</p>
            @error('avatar')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>
        <div class="row-actions">
            <button class="button" type="submit">Save User</button>
            <a class="button button-secondary" href="{{ route('users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
