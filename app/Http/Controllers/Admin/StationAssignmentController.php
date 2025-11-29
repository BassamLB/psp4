<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PollingStation;
use App\Models\StationUserAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StationAssignmentController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = StationUserAssignment::with(['user', 'pollingStation.town', 'assignedBy'])
            ->orderByDesc('created_at');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('pollingStation', function ($q) use ($search) {
                $q->where('station_number', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Active status filter
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === '1');
        }

        $assignments = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/StationAssignments/Index', [
            'assignments' => $assignments,
            'filters' => $request->only(['search', 'role', 'is_active']),
        ]);
    }

    public function create(): \Inertia\Response
    {
        $users = User::where('is_allowed', true)
            ->where('is_blocked', false)
            ->with('role')
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
            ]);

        $pollingStations = PollingStation::with(['town.district.electoralDistrict'])
            ->orderBy('station_number')
            ->get()
            ->map(fn ($station) => [
                'id' => $station->id,
                'station_number' => $station->station_number,
                'station_location' => $station->location,
                'town_name' => $station->town->name,
                'district_name' => $station->town->district->name,
                'electoral_district' => $station->town->district->electoralDistrict->name,
            ]);

        return Inertia::render('admin/StationAssignments/Create', [
            'users' => $users,
            'pollingStations' => $pollingStations,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'polling_station_id' => 'required|exists:polling_stations,id',
            'role' => 'required|in:counter,verifier,supervisor',
        ]);

        // Check if assignment already exists
        $existing = StationUserAssignment::where('user_id', $validated['user_id'])
            ->where('polling_station_id', $validated['polling_station_id'])
            ->where('role', $validated['role'])
            ->first();

        if ($existing) {
            return back()->withErrors(['error' => 'هذا المستخدم مُعيّن بالفعل لهذا القلم بنفس الدور.']);
        }

        StationUserAssignment::create([
            'user_id' => $validated['user_id'],
            'polling_station_id' => $validated['polling_station_id'],
            'role' => $validated['role'],
            'assigned_by' => Auth::id(),
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        return redirect()->route('admin.station-assignments.index')
            ->with('success', 'تم تعيين المستخدم بنجاح.');
    }

    public function destroy(StationUserAssignment $assignment): \Illuminate\Http\RedirectResponse
    {
        $assignment->delete();

        return back()->with('success', 'تم حذف التعيين بنجاح.');
    }

    public function toggleStatus(StationUserAssignment $assignment): \Illuminate\Http\RedirectResponse
    {
        $assignment->update(['is_active' => ! $assignment->is_active]);

        return back()->with('success', 'تم تحديث حالة التعيين بنجاح.');
    }
}
