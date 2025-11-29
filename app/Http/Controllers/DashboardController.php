<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Route to different dashboards based on role
        if ($user->isAdmin()) {
            return $this->adminDashboard($user);
        }

        if ($user->isBox()) {
            return $this->boxDashboard($user);
        }

        return $this->userDashboard($user);
    }

    private function adminDashboard(\App\Models\User $user): Response
    {
        // Get pending approval users count
        $pendingUsers = \App\Models\User::where('is_allowed', false)
            ->where('is_blocked', false)
            ->whereNotNull('password')
            ->count();

        // Get pending device approvals count
        $pendingDevices = \App\Models\UserDevice::where('is_approved', false)->count();

        return Inertia::render('admin/Dashboard', [
            'pendingUsers' => $pendingUsers,
            'pendingDevices' => $pendingDevices,
        ]);
    }

    private function boxDashboard(\App\Models\User $user): Response|RedirectResponse
    {
        \Illuminate\Support\Facades\Log::info('boxDashboard called', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        // Get the user's assigned polling station
        /** @var \App\Models\StationUserAssignment|null $assignment */
        $assignment = $user->stationAssignments()
            ->where('is_active', true)
            ->where('role', 'counter')
            ->with('pollingStation.town')
            ->first();

        // If user has no active assignment, show error message
        if (! $assignment) {
            \Illuminate\Support\Facades\Log::warning('No active assignment found for box user', [
                'user_id' => $user->id,
            ]);

            return Inertia::render('Dashboard', [
                'user' => $user->only(['name', 'email']),
                'error' => 'لم يتم تعيينك إلى قلم اقتراع بعد. يرجى التواصل مع المسؤول.',
            ]);
        }

        \Illuminate\Support\Facades\Log::info('Redirecting to ballot entry', [
            'user_id' => $user->id,
            'station_id' => $assignment->polling_station_id,
        ]);

        // Redirect to the ballot entry page for their assigned station
        return redirect()->route('ballots.entry', ['station' => $assignment->polling_station_id]);
    }

    private function userDashboard(\App\Models\User $user): Response
    {
        return Inertia::render('Dashboard', [
            'user' => $user->only(['name', 'email']),
        ]);
    }
}
