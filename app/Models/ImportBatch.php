<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $table = 'import_batches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'report' => 'array',
        ];
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(VoterUpload::class, 'voter_upload_id');
    }

}
