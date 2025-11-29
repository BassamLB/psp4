<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class StationUserAssignment
 *
 * @property int $id
 * @property int|null $polling_station_id
 * @property int|null $user_id
 * @property string|null $role
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property int|null $assigned_by
 * @property bool $is_active
 * @property-read \App\Models\PollingStation|null $pollingStation
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\User|null $assignedBy
 */
class StationUserAssignment extends Model
{
    protected $fillable = [
        'polling_station_id',
        'user_id',
        'role',
        'assigned_at',
        'assigned_by',
        'is_active',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_active' => 'boolean',
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
