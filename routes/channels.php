<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pos-sync', function (User $user): bool {
    return in_array($user->role, ['admin', 'manager', 'cashier'], true);
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
