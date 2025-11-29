<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BallotEntryLog
 *
 * @property int $id
 * @property int|null $polling_station_id
 * @property int|null $user_id
 * @property string|null $event_type
 * @property array<string,mixed>|null $event_data
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\PollingStation|null $pollingStation
 * @property-read \App\Models\User|null $user
 */
class BallotEntryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'polling_station_id',
        'user_id',
        'event_type',
        'event_data',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\PollingStation, $this>
     */
    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
