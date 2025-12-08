<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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

        if ($user->isDataEditor()) {
            if (Route::has('data-editor.dashboard')) {
                // Use a direct path to avoid static analyzer complaints about missing named routes.
                return redirect()->to('/data-editor');
            }

            Log::warning('data-editor.dashboard route not found; falling back to main dashboard', [
                'user_id' => $user->id,
            ]);

            return redirect()->route('dashboard');
        }

        return $this->userDashboard($user);
    }

    private function adminDashboard(): Response
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
        Log::info('boxDashboard called', [
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
            Log::warning('No active assignment found for box user', [
                'user_id' => $user->id,
            ]);

            return Inertia::render('Dashboard', [
                'user' => $user->only(['name', 'email']),
                'error' => 'لم يتم تعيينك إلى قلم اقتراع بعد. يرجى التواصل مع المسؤول.',
            ]);
        }

        Log::info('Redirecting to ballot entry', [
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
