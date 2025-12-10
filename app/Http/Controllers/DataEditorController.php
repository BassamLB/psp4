<?php

namespace App\Http\Controllers;

use App\Models\Belong;
use App\Models\Country;
use App\Models\Gender;
use App\Models\Profession;
use App\Models\Sect;
use App\Models\Town;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DataEditorController extends Controller
{
    /**
     * Display the data editor dashboard
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isDataEditor()) {
            abort(403, 'غير مخول للوصول إلى هذه الصفحة');
        }

        // Get user's allowed town_ids from region_ids
        $regionIds = $user->region_ids ?? [];

        // Decode JSON if it's a string
        if (is_string($regionIds)) {
            $regionIds = json_decode($regionIds, true) ?? [];
        }

        if (empty($regionIds)) {
            return Inertia::render('data-editor/Dashboard', [
                'voters' => ['data' => [], 'total' => 0],
                'filters' => $this->getFilters(),
                'message' => 'لم يتم تعيين مناطق لحسابك. يرجى التواصل مع المدير.',
            ]);
        }

        // Build the query
        $query = Voter::query()
            ->with(['town', 'gender', 'sect', 'profession', 'country', 'belong'])
            ->whereIn('town_id', $regionIds)
            ->whereNull('deleted_at');

        // Apply filters
        $this->applyFilters($query, $request);

        // Get voters with pagination using infinite scroll (use a named page param)
        $voters = $query->paginate(perPage: 50)->withQueryString();

        return Inertia::render('data-editor/Dashboard', [
            // Pass the paginator directly to Inertia::scroll (avoid wrapping in a closure)
            'voters' => Inertia::scroll(fn() => $voters),
            'filters' => $this->getFilters(),
            'currentFilters' => $request->only([
                'search_sijil',
                'search_family_name',
                'gender_id',
                'sect_id',
                'profession_id',
                'has_belong',
                'is_deceased',
                'is_travelled',
                'town_id',
            ]),
        ]);
    }

    /**
     * Update voter information
     */
    public function update(Request $request, Voter $voter): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isDataEditor()) {
            abort(403, 'غير مخول');
        }

        // Check if voter is in user's allowed regions
        $regionIds = $user->region_ids ?? [];
        if (is_string($regionIds)) {
            $regionIds = json_decode($regionIds, true) ?? [];
        }
        if (! in_array($voter->town_id, $regionIds)) {
            abort(403, 'غير مخول لتعديل هذا الناخب');
        }

        $validatedData = $request->validate([
            'belong_id' => 'nullable|exists:belongs,id',
            'travelled' => 'boolean',
            'country_id' => 'nullable|exists:countries,id',
            'profession_id' => 'nullable|exists:professions,id',
            'deceased' => 'boolean',
            'mobile_number' => 'nullable|string|max:20',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $voter->update($validatedData);

        return redirect()->back()->with('success', 'تم تحديث بيانات الناخب بنجاح');
    }

    /**
     * Move voter to different family (drag and drop)
     */
    public function moveToFamily(Request $request, Voter $voter): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDataEditor()) {
            return response()->json(['message' => 'غير مخول'], 403);
        }

        // Check if voter is in user's allowed regions
        $regionIds = $user->region_ids ?? [];
        if (is_string($regionIds)) {
            $regionIds = json_decode($regionIds, true) ?? [];
        }
        if (! in_array($voter->town_id, $regionIds)) {
            return response()->json(['message' => 'غير مخول لتعديل هذا الناخب'], 403);
        }

        $validatedData = $request->validate([
            'family_id' => 'nullable|exists:families,id',
        ]);

        $voter->update(['family_id' => $validatedData['family_id']]);

        return response()->json([
            'message' => 'تم نقل الناخب للعائلة بنجاح',
            'voter' => $voter->fresh(['town', 'gender', 'sect', 'profession', 'country', 'belong']),
        ]);
    }

    /**
     * Get families for the current user's regions
     */
    public function getFamilies(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDataEditor()) {
            return response()->json(['message' => 'غير مخول'], 403);
        }

        $regionIds = $user->region_ids ?? [];
        if (is_string($regionIds)) {
            $regionIds = json_decode($regionIds, true) ?? [];
        }

        if (empty($regionIds)) {
            return response()->json(['families' => []]);
        }

        $families = DB::table('families')
            ->join('voters', 'families.id', '=', 'voters.family_id')
            ->whereIn('voters.town_id', $regionIds)
            ->select([
                'families.id',
                'families.canonical_name',
                'families.town_id',
                DB::raw('COUNT(voters.id) as members_count'),
            ])
            ->groupBy(['families.id', 'families.canonical_name', 'families.town_id'])
            ->get();

        return response()->json(['families' => $families]);
    }

    /**
     * Apply filters to the query
     */
    protected function applyFilters($query, Request $request): void
    {
        if ($request->filled('search_sijil')) {
            $query->where('sijil_number', $request->search_sijil);
        }

        if ($request->filled('search_family_name')) {
            $query->where('family_name', 'like', '%'.$request->search_family_name.'%');
        }

        if ($request->filled('gender_id')) {
            $query->where('gender_id', $request->gender_id);
        }

        if ($request->filled('sect_id')) {
            $query->where('sect_id', $request->sect_id);
        }

        if ($request->filled('profession_id')) {
            $query->where('profession_id', $request->profession_id);
        }

        if ($request->filled('town_id')) {
            $query->where('town_id', $request->town_id);
        }

        if ($request->filled('has_belong')) {
            if ($request->has_belong === 'yes') {
                $query->whereNotNull('belong_id');
            } elseif ($request->has_belong === 'no') {
                $query->whereNull('belong_id');
            }
        }

        if ($request->filled('is_deceased')) {
            $query->where('deceased', $request->is_deceased === 'yes');
        }

        if ($request->filled('is_travelled')) {
            $query->where('travelled', $request->is_travelled === 'yes');
        }
    }

    /**
     * Get filter options
     */
    protected function getFilters(): array
    {
        return [
            'genders' => Gender::select('id', 'name')->get(),
            'sects' => Sect::select('id', 'name')->get(),
            'professions' => Profession::select('id', 'name')->get(),
            'countries' => Country::select('id', 'name_ar as name')->get(),
            'belongs' => Belong::select('id', 'name')->get(),
            'towns' => Town::select('id', 'name')->get(),
        ];
    }
}
