<?php

namespace App\Events;

use App\Models\VoterUpload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoterUploadCleaned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public VoterUpload $upload;

    public function __construct(VoterUpload $upload)
    {
        $this->upload = $upload;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.uploads');
    }

    /**
     * @return array{
     *     id: int,
     *     filename: string,
     *     status: string,
     *     meta: mixed,
     *     created_at: string|null
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->upload->id,
            'filename' => $this->upload->filename,
            'status' => $this->upload->status,
            'meta' => $this->upload->meta ?? null,
            'created_at' => $this->upload->updated_at?->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'VoterUploadCleaned';
    }
}
