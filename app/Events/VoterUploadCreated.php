<?php

namespace App\Events;

use App\Models\VoterUpload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoterUploadCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public VoterUpload $upload;

    /**
     * Create a new event instance.
     */
    public function __construct(VoterUpload $upload)
    {
        $this->upload = $upload;
    }

    /**
     * The channel the event should broadcast on.
     * Using a public channel named `uploads` so clients can subscribe.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.uploads');
    }

    /**
     * Customize the broadcast payload.
     *
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->upload->id,
            'filename' => $this->upload->filename,
            'path' => $this->upload->path,
            'size' => $this->upload->size,
            'status' => $this->upload->status,
            'created_at' => $this->upload->created_at?->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'VoterUploadCreated';
    }
}
