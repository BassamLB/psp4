<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrackUserDevice
{
    /**
     * @return array<string,mixed>
     */
    public function handle(Authenticatable $user, Request $request): array
    {
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('Expected App\\Models\\User instance');
        }
        $deviceId = $this->generateDeviceId($request);
        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        $isNewDevice = ! $device;

        if (! $device) {
            // New device detected
            $device = UserDevice::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_name' => $this->getDeviceName($request),
                'device_type' => $this->getDeviceType($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_approved' => $user->isAdmin(), // Auto-approve for admin
                'is_current' => true,
                'last_used_at' => now(),
            ]);

            // Mark all other devices as not current
            UserDevice::where('user_id', $user->id)
                ->where('id', '!=', $device->id)
                ->update(['is_current' => false]);
        } else {
            // Update existing device
            $device->update([
                'ip_address' => $request->ip(),
                'last_used_at' => now(),
                'is_current' => true,
            ]);

            // Mark all other devices as not current
            UserDevice::where('user_id', $user->id)
                ->where('id', '!=', $device->id)
                ->update(['is_current' => false]);
        }

        return [
            'device' => $device,
            'is_new_device' => $isNewDevice,
            'requires_approval' => ! $device->is_approved && ! $user->isAdmin(),
        ];
    }

    private function generateDeviceId(Request $request): string
    {
        // Create a unique device fingerprint based on user agent and other factors
        $fingerprint = $request->userAgent().$request->header('Accept-Language');

        return hash('sha256', $fingerprint);
    }

    private function getDeviceName(Request $request): string
    {
        $userAgent = $request->userAgent();

        // Simple browser detection
        if (Str::contains($userAgent, 'Firefox')) {
            return 'Firefox';
        }
        if (Str::contains($userAgent, 'Chrome')) {
            return 'Chrome';
        }
        if (Str::contains($userAgent, 'Safari')) {
            return 'Safari';
        }
        if (Str::contains($userAgent, 'Edge')) {
            return 'Edge';
        }

        return 'Unknown Browser';
    }

    private function getDeviceType(Request $request): string
    {
        $userAgent = $request->userAgent();

        if (Str::contains($userAgent, ['Mobile', 'Android', 'iPhone'])) {
            return 'mobile';
        }
        if (Str::contains($userAgent, ['Tablet', 'iPad'])) {
            return 'tablet';
        }

        return 'desktop';
    }
}
