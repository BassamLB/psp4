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
