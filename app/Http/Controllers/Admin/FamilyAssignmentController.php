<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AssignFamiliesToVoters;
use App\Models\Voter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FamilyAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index(): InertiaResponse
    {
        // Get statistics
        $totalVoters = DB::table('voters')->count();
        $votersWithFamily = DB::table('voters')->whereNotNull('family_id')->count();
        $votersWithoutFamily = DB::table('voters')->whereNull('family_id')->count();
        $totalFamilies = DB::table('families')->count();

        // Get families with their member counts, sorted by member count descending
        // Build groupBy columns defensively — some test DBs (sqlite in-memory)
        // may run migrations in an order where parent id columns don't exist.
        $groupBy = [
            'families.id',
            'families.canonical_name',
            'families.sijil_number',
            'families.town_id',
            'families.sect_id',
            'families.slug',
            'families.created_at',
            'families.updated_at',
            'towns.name',
        ];

        if (Schema::hasColumn('families', 'father_id')) {
            $groupBy[] = 'families.father_id';
        }

        if (Schema::hasColumn('families', 'mother_id')) {
            $groupBy[] = 'families.mother_id';
        }

        $families = DB::table('families')
            ->select('families.*', 'towns.name as town_name', DB::raw('COUNT(voters.id) as members_count'))
            ->leftJoin('voters', 'families.id', '=', 'voters.family_id')
            ->leftJoin('towns', 'families.town_id', '=', 'towns.id')
            ->groupBy($groupBy)
            ->orderByDesc('members_count')
            ->limit(20)
            ->get()
            ->map(function ($family) {
                $selectCols = ['id', 'first_name', 'father_name', 'family_name', 'mother_full_name', 'gender_id'];

                // Use null-coalescing to avoid accessing missing properties when
                // migrations haven't added parent id columns in the test DB.
                $fatherId = $family->father_id ?? null;
                $motherId = $family->mother_id ?? null;

                $father = null;
                if (! empty($fatherId)) {
                    $father = Voter::where('id', $fatherId)
                        ->select($selectCols)
                        ->first();
                }

                $mother = null;
                if (! empty($motherId)) {
                    $mother = Voter::where('id', $motherId)
                        ->select($selectCols)
                        ->first();
                }

                $childrenQuery = Voter::where('family_id', $family->id)
                    ->select($selectCols)
                    ->orderBy('first_name');

                if (! empty($fatherId)) {
                    $childrenQuery->where('id', '!=', $fatherId);
                }

                if (! empty($motherId)) {
                    $childrenQuery->where('id', '!=', $motherId);
                }

                $children = $childrenQuery->get();

                $members = collect();
                if ($father) {
                    $fatherArr = $father->toArray();
                    $fatherArr['role'] = 'father';
                    $fatherArr['gender_name'] = DB::table('genders')->where('id', $fatherArr['gender_id'])->value('name');
                    $members->push($fatherArr);
                }

                if ($mother) {
                    $motherArr = $mother->toArray();
                    $motherArr['role'] = 'mother';
                    $motherArr['gender_name'] = DB::table('genders')->where('id', $motherArr['gender_id'])->value('name');
                    $members->push($motherArr);
                }

                foreach ($children as $child) {
                    $childArr = $child->toArray();
                    $childArr['role'] = 'child';
                    $childArr['gender_name'] = DB::table('genders')->where('id', $childArr['gender_id'])->value('name');
                    $members->push($childArr);
                }

                return [
                    'id' => $family->id,
                    'canonical_name' => $family->canonical_name,
                    'sijil_number' => $family->sijil_number,
                    'town_name' => $family->town_name ?? null,
                    'members_count' => $family->members_count,
                    'members' => $members->values(),
                ];
            });

        return Inertia::render('admin/FamilyAssignment/Index', [
            'stats' => [
                'total_voters' => $totalVoters,
                'voters_with_family' => $votersWithFamily,
                'voters_without_family' => $votersWithoutFamily,
                'total_families' => $totalFamilies,
                'percentage_assigned' => $totalVoters > 0 ? round(($votersWithFamily / $totalVoters) * 100, 2) : 0,
            ],
            'families' => $families,
            // Backwards-compatibility for tests and any consumers expecting
            // the older `sample_families` prop name.
            'sample_families' => $families,
        ]);
    }

    public function assign(): RedirectResponse
    {
        try {
            // Dispatch the family assignment job
            AssignFamiliesToVoters::dispatch(['incremental' => true])->onQueue('imports');

            return redirect()->route('admin.family-assignment.index')
                ->with('success', 'تم إطلاق عملية تعيين العائلات بنجاح. سيتم معالجة الناخبين في الخلفية.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.family-assignment.index')
                ->with('error', 'حدث خطأ أثناء إطلاق عملية تعيين العائلات: '.$e->getMessage());
        }
    }

    public function assignAll(): RedirectResponse
    {
        try {
            // Dispatch the family assignment job for all voters
            AssignFamiliesToVoters::dispatch([])->onQueue('imports');

            return redirect()->route('admin.family-assignment.index')
                ->with('success', 'تم إطلاق عملية إعادة تعيين جميع العائلات بنجاح. سيتم معالجة جميع الناخبين في الخلفية.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.family-assignment.index')
                ->with('error', 'حدث خطأ أثناء إطلاق عملية تعيين العائلات: '.$e->getMessage());
        }
    }
}
