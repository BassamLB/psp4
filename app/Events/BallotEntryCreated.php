<?php

namespace App\Events;

use App\Models\BallotEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BallotEntryCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public BallotEntry $ballotEntry) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('station.'.$this->ballotEntry->polling_station_id),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->ballotEntry->id,
            'ballot_type' => $this->ballotEntry->ballot_type,
            'list_id' => $this->ballotEntry->list_id,
            'candidate_id' => $this->ballotEntry->candidate_id,
            'entered_at' => $this->ballotEntry->entered_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ballot.created';
    }
}
