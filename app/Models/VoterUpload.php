<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoterUpload extends Model
{
    protected $table = 'voter_uploads';

    protected $fillable = [
        'filename',
        'original_name',
        'path',
        'size',
        'status',
        'user_id',
        'job_id',
        'meta',
    ];

    protected $casts = [
        'size' => 'integer',
        'meta' => 'array',
    ];

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    public function markProcessing(): void
    {
        $this->status = 'processing';
        $this->save();
    }

    public function markDone(): void
    {
        $this->status = 'done';
        $this->save();
    }

    public function markFailed(): void
    {
        $this->status = 'failed';
        $this->save();
    }
}
