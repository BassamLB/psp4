<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBallotEntryRequest;
use App\Jobs\ProcessBallotEntry;
use App\Models\BallotEntryLog;
use App\Models\PollingStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class BallotController extends Controller
{
    public function store(StoreBallotEntryRequest $request, PollingStation $station): JsonResponse
    {
        // Rate limiting: 60 entries per minute per user
        $key = 'ballot-entry:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 60)) {
            $seconds = RateLimiter::availableIn($key);

            BallotEntryLog::insert([
                'polling_station_id' => $station->id,
                'user_id' => $request->user()->id,
                'event_type' => 'suspicious_activity',
                'event_data' => json_encode(['reason' => 'rate_limit_exceeded']),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'تم تجاوز الحد المسموح. يرجى الانتظار.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, 60);

        // Dispatch job to process ballot entry
        ProcessBallotEntry::dispatch(
            $request->validated(),
            $station->id,
            $request->user()->id,
            $request->ip()
        );

        return response()->json([
            'message' => 'تم إدخال الورقة بنجاح',
            'queued' => true,
        ], 202);
    }

    public function index(PollingStation $station): JsonResponse
    {
        $entries = $station->ballotEntries()
            ->with(['list', 'candidate', 'enteredBy:id,name'])
            ->latest('entered_at')
            ->paginate(50);

        return response()->json($entries);
    }
}
