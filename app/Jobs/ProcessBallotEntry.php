<?php

namespace App\Jobs;

use App\Events\BallotEntryCreated;
use App\Models\BallotEntry;
use App\Models\BallotEntryLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBallotEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $maxExceptions = 2;

    /** @var int[] Retry after 5,10,20 seconds */
    public array $backoff = [5, 10, 20];

    /**
     * @param  array<string,mixed>  $data
     */
    public function __construct(
        public array $data,
        public int $pollingStationId,
        public int $userId,
        public ?string $ipAddress = null
    ) {
        $this->onQueue('ballot-entry');
    }

    public function handle(): void
    {
        try {
            $entry = null;

            DB::transaction(function () use (&$entry) {
                // Create ballot entry
                $entry = BallotEntry::create([
                    'polling_station_id' => $this->pollingStationId,
                    'list_id' => $this->data['list_id'] ?? null,
                    'candidate_id' => $this->data['candidate_id'] ?? null,
                    'ballot_type' => $this->data['ballot_type'],
                    'cancellation_reason' => $this->data['cancellation_reason'] ?? null,
                    'entered_by' => $this->userId,
                    'entered_at' => now(),
                    'ip_address' => $this->ipAddress,
                    'metadata' => $this->data['metadata'] ?? null,
                ]);

                // Log the event (DB insert) inside the transaction so it rolls back with the entry if needed
                BallotEntryLog::insert([
                    'polling_station_id' => $this->pollingStationId,
                    'user_id' => $this->userId,
                    'event_type' => 'entry_created',
                    'event_data' => json_encode([
                        'ballot_entry_id' => $entry->id,
                        'ballot_type' => $entry->ballot_type,
                        'list_id' => $entry->list_id,
                        'candidate_id' => $entry->candidate_id,
                    ]),
                    'ip_address' => $this->ipAddress,
                    'created_at' => now(),
                ]);
            });

            // Broadcast and schedule aggregation after the transaction commits to ensure other workers see the data.
            if ($entry) {
                Log::info('Broadcasting BallotEntryCreated event', [
                    'entry_id' => $entry->id,
                    'station_id' => $entry->polling_station_id,
                ]);

                broadcast(new BallotEntryCreated($entry));

                // Dispatch aggregation job after commit — schedule with a short delay to debounce
                AggregateStationResults::dispatch($this->pollingStationId)
                    ->delay(now()->addSeconds(5))
                    ->onQueue('aggregation');
            }
        } catch (\Exception $e) {
            Log::error('Failed to process ballot entry', [
                'station_id' => $this->pollingStationId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Ballot entry job failed permanently', [
            'station_id' => $this->pollingStationId,
            'user_id' => $this->userId,
            'data' => $this->data,
            'error' => $exception->getMessage(),
        ]);

        // Log the failure
        BallotEntryLog::insert([
            'polling_station_id' => $this->pollingStationId,
            'user_id' => $this->userId,
            'event_type' => 'entry_failed',
            'event_data' => json_encode([
                'error' => $exception->getMessage(),
                'data' => $this->data,
            ]),
            'ip_address' => $this->ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * @return string[]
     */
    public function tags(): array
    {
        return ['ballot-entry', 'station:'.$this->pollingStationId];
    }
}
