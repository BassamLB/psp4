<?php

namespace App\Events;

use App\Models\VoterUpload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoterUploadProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public VoterUpload $upload;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(VoterUpload $upload, array $payload = [])
    {
        $this->upload = $upload;
        $this->payload = $payload;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.uploads');
    }

    /**
     * @return array{id: int, filename: string, status: string} & array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->upload->id,
            'filename' => $this->upload->filename,
            'status' => $this->upload->status,
            'payload' => $this->payload,
        ];
    }

    public function broadcastAs(): string
    {
        return 'VoterUploadProgress';
    }
}
