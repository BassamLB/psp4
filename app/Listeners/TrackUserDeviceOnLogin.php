<?php

namespace App\Listeners;

use App\Actions\TrackUserDevice;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

class TrackUserDeviceOnLogin
{
    public function __construct(
        private TrackUserDevice $trackUserDevice
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        $request = request();

        // Track the device
        $deviceInfo = $this->trackUserDevice->handle($user, $request);

        // Update last login
        $user->update(['last_login_at' => now()]);

        // If device requires approval, log them out immediately
        if ($deviceInfo['requires_approval']) {
            Auth::logout();
            session()->flash('error', 'هذا الجهاز يحتاج إلى موافقة المسؤول. يرجى الانتظار.');
        }
    }
}
