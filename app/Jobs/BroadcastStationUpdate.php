<?php

namespace App\Jobs;

use App\Events\StationResultsUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastStationUpdate
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string,mixed>|null  $data
     */
    public function __construct(public int $pollingStationId, public ?array $data = null) {}

    public function handle(): void
    {
        $payload = is_array($this->data) ? $this->data : [];

        event(new StationResultsUpdated($this->pollingStationId, $payload));
    }
}
