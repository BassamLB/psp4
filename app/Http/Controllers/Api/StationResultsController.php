<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PollingStation;
use App\Models\StationAggregate;
use App\Models\StationSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StationResultsController extends Controller
{
    public function show(PollingStation $station): JsonResponse
    {
        // Get from cache first (updated by AggregateStationResults job)
        $summary = Cache::get("station:{$station->id}:summary");
        $aggregates = Cache::get("station:{$station->id}:aggregates");

        // Fallback to database if cache is empty
        if (! $summary || ! $aggregates) {
            $summary = StationSummary::where('polling_station_id', $station->id)->first();

            $aggregates = StationAggregate::where('polling_station_id', $station->id)
                ->with(['list:id,name', 'candidate:id,full_name'])
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
                })
                ->toArray();

            $summary = $summary ? [
                'total_ballots_entered' => $summary->total_ballots_entered,
                'valid_list_votes' => $summary->valid_list_votes,
                'valid_preferential_votes' => $summary->valid_preferential_votes,
                'white_papers' => $summary->white_papers,
                'cancelled_papers' => $summary->cancelled_papers,
            ] : null;
        }

        return response()->json([
            'summary' => $summary,
            'aggregates' => $aggregates,
        ]);
    }

    public function summary(PollingStation $station): JsonResponse
    {
        $summary = Cache::remember(
            "station:{$station->id}:summary",
            30,
            fn () => StationSummary::where('polling_station_id', $station->id)->first()
        );

        return response()->json($summary);
    }

    public function aggregates(PollingStation $station): JsonResponse
    {
        $aggregates = Cache::remember(
            "station:{$station->id}:aggregates",
            30,
            fn () => StationAggregate::where('polling_station_id', $station->id)
                ->with(['list:id,name,number,color', 'candidate:id,full_name,position_on_list'])
                ->orderByDesc('vote_count')
                ->get()
        );

        return response()->json($aggregates);
    }
}
