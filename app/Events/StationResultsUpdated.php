<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StationResultsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string,mixed>|null  $data
     */
    public function __construct(public int $pollingStationId, public ?array $data = null) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('station.'.$this->pollingStationId),
            new Channel('stations'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        // Only broadcast minimal data - let frontend fetch full details
        return [
            'station_id' => $this->pollingStationId,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'results.updated';
    }
}
