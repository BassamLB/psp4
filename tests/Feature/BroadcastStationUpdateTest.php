<?php

use App\Events\StationResultsUpdated;
use App\Jobs\BroadcastStationUpdate;
use Illuminate\Support\Facades\Event;

it('broadcasts station results with provided data', function () {
    Event::fake();

    $job = new BroadcastStationUpdate(123, ['votes' => 10]);
    $job->handle();

    Event::assertDispatched(StationResultsUpdated::class, function ($e) {
        return $e->pollingStationId === 123
            && isset($e->data['votes'])
            && $e->data['votes'] === 10;
    });
});

it('handles null data by broadcasting empty payload', function () {
    Event::fake();

    $job = new BroadcastStationUpdate(5, null);
    $job->handle();

    Event::assertDispatched(StationResultsUpdated::class, function ($e) {
        return $e->pollingStationId === 5
            && is_array($e->data)
            && count($e->data) === 0;
    });
});
