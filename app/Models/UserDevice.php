<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class UserDevice
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $device_id
 * @property string|null $device_name
 * @property string|null $device_type
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $is_approved
 * @property bool $is_current
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property-read \App\Models\User|null $user
 */
class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'device_type',
        'ip_address',
        'user_agent',
        'is_approved',
        'is_current',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_current' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
