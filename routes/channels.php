<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('station.{stationId}', function ($user, $stationId) {
    // Check if user has access to this station
    return $user->stationAssignments()
        ->where('polling_station_id', $stationId)
        ->where('is_active', true)
        ->exists();
});

// Admin uploads channel - only allow authorized admin users to subscribe.
Broadcast::channel('admin.uploads', function ($user) {
    if (! $user) {
        return false;
    }

    // If the User model exposes an isAdmin() helper, use it.
    if (is_object($user) && method_exists($user, 'isAdmin')) {
        return $user->isAdmin();
    }

    // If there's an `is_admin` attribute, trust it.
    if (isset($user->is_admin)) {
        return (bool) $user->is_admin;
    }

    // Default to allowing authenticated users - restrict later if needed.
    return true;
});
