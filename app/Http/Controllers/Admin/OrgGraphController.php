<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Branch;
use App\Models\Delegate;
use App\Models\Town;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrgGraphController extends Controller
{
    public function index(): \Inertia\Response
    {
        // Eager load the basic hierarchy: agencies -> delegates -> branches -> towns -> district
        $agencies = Agency::with(['delegates.branches.towns.district'])->get();

        // Convert to simple array structure for the frontend
        $graph = $agencies->map(function ($agency) {
            return [
                'id' => $agency->id,
                'name' => $agency->name,
                'delegates' => $agency->delegates->map(function ($delegate) {
                    return [
                        'id' => $delegate->id,
                        'name' => $delegate->name,
                        'branches' => $delegate->branches->map(function ($branch) {
                            return [
                                'id' => $branch->id,
                                'name' => $branch->name,
                                'towns' => $branch->towns->map(function ($town) {
                                    return [
                                        'id' => $town->id,
                                        'name' => $town->name,
                                        'electoral_district_id' => $town->district->electoral_district_id ?? null,
                                        'district' => $town->district ? [
                                            'id' => $town->district->id,
                                            'name' => $town->district->name,
                                            'electoral_district_id' => $town->district->electoral_district_id,
                                        ] : null,
                                    ];
                                })->toArray(),
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ];
        })->toArray();

        // Also return an unlinked list of towns for convenience (all towns). Include district relation so we can expose electoral_district_id.
        $towns = Town::with('district')->get()->map(function ($town) {
            return [
                'id' => $town->id,
                'name' => $town->name,
                'electoral_district_id' => $town->district->electoral_district_id ?? null,
                'district' => $town->district ? [
                    'id' => $town->district->id,
                    'name' => $town->district->name,
                    'electoral_district_id' => $town->district->electoral_district_id,
                ] : null,
            ];
        });

        return Inertia::render('admin/OrgGraph', [
            'graph' => $graph,
            'towns' => $towns,
        ]);
    }

    public function attachTown(Branch $branch, Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['town_id' => ['required', 'integer', 'exists:towns,id']]);

        $town = Town::findOrFail($request->input('town_id'));
        /** @var \App\Models\Town $town */
        $town->branch_id = $branch->id;
        $town->save();

        return response()->json(['status' => 'attached'], 200);
    }

    public function detachTown(Branch $branch, Town $town): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\Town $town */
        if ($town->branch_id === $branch->id) {
            $town->branch_id = null;
            $town->save();
        }

        return response()->json(null, 204);
    }

    public function delegateDistrictTowns(Delegate $delegate): \Illuminate\Http\JsonResponse
    {
        // Compute electoral district ids from towns attached to this delegate's branches
        $townsForDelegate = Town::whereHas('branch', function ($q) use ($delegate) {
            $q->where('delegate_id', $delegate->id);
        })->with('district')->get();

        $districtIds = $townsForDelegate->map(fn ($t) => $t->district->electoral_district_id)
            ->unique()
            ->filter()
            ->toArray();

        // Find towns that belong to those electoral districts
        $towns = Town::whereHas('district', function ($q) use ($districtIds) {
            $q->whereIn('electoral_district_id', $districtIds);
        })->with('district')->get()->map(function ($town) {
            return [
                'id' => $town->id,
                'name' => $town->name,
                'electoral_district_id' => $town->district->electoral_district_id ?? null,
                'district' => $town->district ? [
                    'id' => $town->district->id,
                    'name' => $town->district->name,
                    'electoral_district_id' => $town->district->electoral_district_id,
                ] : null,
            ];
        });

        return response()->json($towns);
    }
}
