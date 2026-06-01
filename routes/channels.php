<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.updates', function ($user) {
    return in_array($user->role, ['admin', 'super_admin', 'maintenance_staff', 'landlord']);
});

Broadcast::channel('user.updates.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
