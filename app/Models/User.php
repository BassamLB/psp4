<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Class User
 *
 * @property int|null $role_id
 * @property bool $is_active
 * @property bool $is_allowed
 * @property bool $is_blocked
 * @property int|null $responsible_id
 * @property-read \App\Models\Role|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UserDevice[] $devices
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StationUserAssignment[] $stationAssignments
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\BallotEntry[] $ballotEntries
 *
 * @method bool isBox()
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile_number',
        'registration_code',
        'role_id',
        'is_active',
        'is_allowed',
        'is_blocked',
        'last_login_at',
        'responsible_id',
        'region_ids',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'region_ids' => 'array',
            'is_active' => 'boolean',
            'is_allowed' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\StationUserAssignment, $this>
     */
    public function stationAssignments(): HasMany
    {
        return $this->hasMany(StationUserAssignment::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\BallotEntry, $this>
     */
    public function ballotEntries(): HasMany
    {
        return $this->hasMany(BallotEntry::class, 'entered_by');
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'مدير';
    }

    public function isApproved(): bool
    {
        return $this->is_allowed && ! $this->is_blocked;
    }

    public function isBox(): bool
    {
        return $this->role?->name === 'مندوب صندوق';
    }
}
