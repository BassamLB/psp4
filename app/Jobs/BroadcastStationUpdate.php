<?php

namespace App\Jobs;

use App\Events\StationResultsUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BroadcastStationUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * Optional data payload to include in the broadcast.
     *
     * @var array<string,mixed>|null
     */
    public ?array $data = null;

    /**
     * Number of attempts for the job.
     */
    public int $tries = 3;

    /**
     * Maximum runtime for the job.
     */
    public int $timeout = 30;

    /**
     * @param  array<string,mixed>|null  $data
     */
    public function __construct(public int $pollingStationId, ?array $data = null)
    {
        $this->data = $data;
        $this->onQueue('broadcasting');
    }

    public function handle(): void
    {
        try {
            $payload = $this->normalizePayload($this->data);

            broadcast(new StationResultsUpdated($this->pollingStationId, $payload));
        } catch (\Throwable $e) {
            Log::error('BroadcastStationUpdate failed', [
                'polling_station_id' => $this->pollingStationId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Normalize various payload types into an associative array.
     *
     * @return array<string,mixed>
     */
    protected function normalizePayload(mixed $payload): array
    {
        if (is_null($payload)) {
            return [];
        }

        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return ['value' => $payload];
        }

        if (is_object($payload) && method_exists($payload, 'toArray')) {
            return $payload->toArray();
        }

        return (array) $payload;
    }

    /**
     * @return string[]
     */
    public function tags(): array
    {
        return ['broadcast', 'station:'.$this->pollingStationId];
    }
}
