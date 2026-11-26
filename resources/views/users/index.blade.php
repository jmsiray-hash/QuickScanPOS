@extends('layouts.app')

@section('title', 'Users | QuickScanPos')

@section('content')
    <div class="page-actions">
        <h2>User List</h2>
        <a class="button" href="{{ route('users.create') }}">Create User</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Avatar</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>
                            <img
                                class="avatar"
                                src="{{ $user->avatar ? route('users.avatar', $user) : asset('images/avatar-placeholder.svg') }}"
                                alt="{{ $user->full_name }} avatar"
                                width="56"
                                height="56"
                                onerror="this.onerror=null;this.src='{{ asset('images/avatar-placeholder.svg') }}';"
                            >
                        </td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->full_name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ ucfirst($user->role) }}</td>
                        <td>{{ $user->created_at }}</td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('users.show', $user) }}">View</a>
                                <a href="{{ route('users.edit', $user) }}">Edit</a>
                                <form action="{{ route('users.destroy', $user) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
