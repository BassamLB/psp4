<?php

namespace App\Http\Controllers;

use App\Models\ElectoralList;
use App\Models\PollingStation;
use App\Models\StationAggregate;
use App\Models\StationSummary;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BallotEntryController extends Controller
{
    public function show(PollingStation $station): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401, 'غير مصرح');
        }

        // Check if user has the correct role
        if (! $user->isBox()) {
            \Illuminate\Support\Facades\Log::warning('User is not a box delegate', [
                'user_id' => $user->id,
                'role_name' => $user->role->name ?? 'No role',
            ]);
            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة.');
        }

        // Check if user is assigned to this station
        $assignment = $user->stationAssignments()
            ->where('polling_station_id', $station->id)
            ->where('is_active', true)
            ->where('role', 'counter')
            ->first();

        if (! $assignment) {
            \Illuminate\Support\Facades\Log::warning('User not assigned to station', [
                'user_id' => $user->id,
                'station_id' => $station->id,
                'user_assignments' => $user->stationAssignments()->pluck('polling_station_id')->toArray(),
            ]);
            abort(403, 'أنت غير مُعيّن لهذا القلم.');
        }

        // Load necessary relationships
        $station->load('town.district.electoralDistrict');

        // Get lists for the station's electoral district
        $lists = ElectoralList::where('electoral_district_id', $station->town->district->electoral_district_id)
            ->with(['candidates' => function ($query) {
                $query->orderBy('position_on_list');
            }])
            ->get()
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'color' => $list->color ?? '#3B82F6',
                    'number' => $list->number ?? 0,
                    'candidates' => $list->candidates->map(function ($candidate) {
                        return [
                            'id' => $candidate->id,
                            'full_name' => $candidate->full_name,
                        ];
                    }),
                ];
            });

        // Get current summary
        $summary = StationSummary::where('polling_station_id', $station->id)->first() ?? new StationSummary([
            'total_ballots_entered' => 0,
            'valid_list_votes' => 0,
            'valid_preferential_votes' => 0,
            'white_papers' => 0,
            'cancelled_papers' => 0,
        ]);

        // Get aggregates
        $aggregates = StationAggregate::where('polling_station_id', $station->id)
            ->with(['list', 'candidate'])
            ->orderByDesc('vote_count')
            ->get()
            ->map(function ($agg) {
                return [
                    'list_id' => $agg->list_id,
                    'candidate_id' => $agg->candidate_id,
                    'vote_count' => $agg->vote_count,
                    'list' => $agg->list ? ['name' => $agg->list->name] : null,
                    'candidate' => $agg->candidate ? ['full_name' => $agg->candidate->full_name] : null,
                ];
            });

        return Inertia::render('Ballots/EntryGrid', [
            'station' => [
                'id' => $station->id,
                'station_number' => $station->station_number,
                'location' => $station->location,
                'town' => [
                    'name' => $station->town->name,
                ],
            ],
            'lists' => $lists,
            'summary' => [
                'total_ballots_entered' => $summary->total_ballots_entered ?? 0,
                'valid_list_votes' => $summary->valid_list_votes ?? 0,
                'valid_preferential_votes' => $summary->valid_preferential_votes ?? 0,
                'white_papers' => $summary->white_papers ?? 0,
                'cancelled_papers' => $summary->cancelled_papers ?? 0,
            ],
            'aggregates' => $aggregates,
        ]);
    }

    public function showGrid(PollingStation $station): Response
    {
        // Both methods do the same thing, so just call show()
        return $this->show($station);
    }
}
