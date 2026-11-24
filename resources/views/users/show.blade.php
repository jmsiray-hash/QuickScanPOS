@extends('layouts.app')

@section('title', 'User Details | QuickScanPos')

@section('content')
    <div class="page-actions">
        <h2>User Details</h2>
        <div class="row-actions">
            <a class="button" href="{{ route('users.edit', $user) }}">Edit User</a>
            <a class="button button-secondary" href="{{ route('users.index') }}">Back</a>
        </div>
    </div>
    <img
        class="avatar avatar-preview"
        src="{{ $user->avatar ? route('users.avatar', $user) : asset('images/avatar-placeholder.svg') }}"
        alt="{{ $user->full_name }} avatar"
        width="120"
        height="120"
    >
    <dl>
        <dt>Username</dt><dd>{{ $user->username }}</dd>
        <dt>Full Name</dt><dd>{{ $user->full_name }}</dd>
        <dt>Email</dt><dd>{{ $user->email }}</dd>
        <dt>Created</dt><dd>{{ $user->created_at }}</dd>
    </dl>
@endsection
