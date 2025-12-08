<?php

namespace App\Events;

use App\Models\VoterUpload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoterUploadImported implements ShouldBroadcastNow
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
     *     created_at: string|null,
     *     message: string
     * }
     */
    public function broadcastWith(): array
    {
        $meta = $this->upload->meta ?? [];
        $report = $meta['import_report'] ?? [];

        return [
            'id' => $this->upload->id,
            'filename' => $this->upload->filename,
            'status' => $this->upload->status,
            'meta' => $meta,
            'created_at' => $this->upload->updated_at?->toIso8601String(),
            'message' => 'Import completed successfully',
            'report' => $report,
        ];
    }

    public function broadcastAs(): string
    {
        return 'VoterUploadImported';
    }
}
