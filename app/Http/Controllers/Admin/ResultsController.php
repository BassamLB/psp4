<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BallotEntry;
use App\Models\District;
use App\Models\ElectoralDistrict;
use App\Models\ElectoralList;
use App\Models\PollingStation;
use App\Models\Town;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('admin/Results');
    }

    public function data(Request $request): \Illuminate\Http\JsonResponse
    {
        $level = $request->get('level');
        $id = $request->get('id');

        // Determine which electoral districts are tracked and restrict stations to them
        $trackedEdIds = ElectoralDistrict::where('is_tracked', true)->pluck('id')->toArray();
        $trackedDistrictIds = District::whereIn('electoral_district_id', $trackedEdIds)->pluck('id')->toArray();
        $trackedTownIds = Town::whereIn('district_id', $trackedDistrictIds)->pluck('id')->toArray();
        $trackedStationIds = PollingStation::whereIn('town_id', $trackedTownIds)->pluck('id')->toArray();

        // Determine station ids to include based on level and id
        $stationIds = null; // null -> all stations (we'll restrict to tracked below)

        if ($level === 'electoral_district' && $id) {
            // if the requested electoral district is not tracked, return empty
            if (! in_array($id, $trackedEdIds)) {
                return response()->json([
                    'labels' => [],
                    'data' => [],
                    'children' => [],
                ]);
            }
            $districtIds = District::where('electoral_district_id', $id)->pluck('id')->toArray();
            $townIds = Town::whereIn('district_id', $districtIds)->pluck('id')->toArray();
            $stationIds = PollingStation::whereIn('town_id', $townIds)->pluck('id')->toArray();
        } elseif ($level === 'district' && $id) {
            $townIds = Town::where('district_id', $id)->pluck('id')->toArray();
            $stationIds = PollingStation::whereIn('town_id', $townIds)->pluck('id')->toArray();
        } elseif ($level === 'town' && $id) {
            $stationIds = PollingStation::where('town_id', $id)->pluck('id')->toArray();
        } elseif ($level === 'station' && $id) {
            $stationIds = [$id];
        }

        // If no specific station filter provided, default to tracked stations only.
        if ($stationIds === null) {
            $stationIds = $trackedStationIds;
        } else {
            // Intersect with tracked stations so deeper-level filters still obey tracking
            $stationIds = array_values(array_intersect($stationIds, $trackedStationIds));
        }

        // Build base electoral list aggregates
        $listsQuery = ElectoralList::select('electoral_lists.id', 'electoral_lists.name', 'electoral_lists.color', DB::raw('COUNT(ballot_entries.id) as votes'))
            ->leftJoin('ballot_entries', 'electoral_lists.id', '=', 'ballot_entries.list_id')
            ->whereIn('ballot_entries.ballot_type', ['valid_list', 'valid_preferential']);

        if (count($stationIds) === 0) {
            // No stations under this filter -> zero results
            $listTotals = collect();
        } else {
            $listsQuery->whereIn('ballot_entries.polling_station_id', $stationIds);
            $listTotals = $listsQuery->groupBy('electoral_lists.id', 'electoral_lists.name')->orderByDesc('votes')->get();
        }

        // Prepare chart-friendly labels/data/colors
        $labels = $listTotals->pluck('name');
        $data = $listTotals->pluck('votes');
        $colors = $listTotals->pluck('color')->map(function ($c) {
            return $c ?: null;
        });

        // Prepare children breakdown depending on current level
        $children = [];

        if (! $level) {
            // Top-level: return only tracked electoral districts as children
            $eds = ElectoralDistrict::where('is_tracked', true)->orderBy('name')->get();
            foreach ($eds as $ed) {
                $districtIds = District::where('electoral_district_id', $ed->id)->pluck('id')->toArray();
                $townIds = Town::whereIn('district_id', $districtIds)->pluck('id')->toArray();
                $stationIdsForEd = PollingStation::whereIn('town_id', $townIds)->pluck('id')->toArray();
                // intersect with trackedStationIds to ensure only tracked station votes are counted
                $stationIdsForEd = array_values(array_intersect($stationIdsForEd, $trackedStationIds));
                $votes = count($stationIdsForEd) ? BallotEntry::validVotes()->whereIn('polling_station_id', $stationIdsForEd)->count() : 0;
                $children[] = [
                    'id' => $ed->id,
                    'name' => $ed->name,
                    'votes' => $votes,
                    'stations_count' => count($stationIdsForEd),
                    'type' => 'electoral_district',
                ];
            }
        } elseif ($level === 'electoral_district' && $id) {
            // children are Districts under this electoral district
            $districts = District::where('electoral_district_id', $id)->orderBy('name')->get();
            foreach ($districts as $d) {
                $townIds = Town::where('district_id', $d->id)->pluck('id')->toArray();
                $stationIdsForD = PollingStation::whereIn('town_id', $townIds)->pluck('id')->toArray();
                // restrict to tracked stations
                $stationIdsForD = array_values(array_intersect($stationIdsForD, $trackedStationIds));
                $votes = count($stationIdsForD) ? BallotEntry::validVotes()->whereIn('polling_station_id', $stationIdsForD)->count() : 0;
                $children[] = [
                    'id' => $d->id,
                    'name' => $d->name,
                    'votes' => $votes,
                    'stations_count' => count($stationIdsForD),
                    'type' => 'district',
                ];
            }
        } elseif ($level === 'district' && $id) {
            // children are Towns
            $towns = Town::where('district_id', $id)->orderBy('name')->get();
            foreach ($towns as $t) {
                $stationIdsForT = PollingStation::where('town_id', $t->id)->pluck('id')->toArray();
                // restrict to tracked stations
                $stationIdsForT = array_values(array_intersect($stationIdsForT, $trackedStationIds));
                $votes = count($stationIdsForT) ? BallotEntry::validVotes()->whereIn('polling_station_id', $stationIdsForT)->count() : 0;
                $children[] = [
                    'id' => $t->id,
                    'name' => $t->name,
                    'votes' => $votes,
                    'stations_count' => count($stationIdsForT),
                    'type' => 'town',
                ];
            }
        } elseif ($level === 'town' && $id) {
            // children are Polling Stations
            // only include tracked stations here
            $stations = PollingStation::where('town_id', $id)->whereIn('id', $trackedStationIds)->orderBy('station_number')->get();
            foreach ($stations as $s) {
                $votes = BallotEntry::validVotes()->where('polling_station_id', $s->id)->count();
                $children[] = [
                    'id' => $s->id,
                    'name' => 'مركز الاقتراع رقم '.$s->station_number,
                    'votes' => $votes,
                    'stations_count' => 1,
                    'type' => 'station',
                ];
            }
        } elseif ($level === 'station' && $id) {
            // station detail: show candidate-level breakdown
            $candidates = DB::table('candidates')
                ->select('candidates.id', 'candidates.name', DB::raw('COUNT(ballot_entries.id) as votes'))
                ->leftJoin('ballot_entries', 'candidates.id', '=', 'ballot_entries.candidate_id')
                ->whereIn('ballot_entries.ballot_type', ['valid_list', 'valid_preferential'])
                ->where('ballot_entries.polling_station_id', $id)
                ->groupBy('candidates.id', 'candidates.name')
                ->orderByDesc('votes')
                ->get();

            foreach ($candidates as $c) {
                $children[] = ['id' => $c->id, 'name' => $c->name, 'votes' => $c->votes, 'type' => 'candidate'];
            }
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
            'children' => $children,
        ]);
    }
}
