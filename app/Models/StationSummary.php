<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class StationSummary
 *
 * @property int $id
 * @property int|null $polling_station_id
 * @property int|null $total_ballots_entered
 * @property int|null $valid_list_votes
 * @property int|null $valid_preferential_votes
 * @property int|null $white_papers
 * @property int|null $cancelled_papers
 * @property \Illuminate\Support\Carbon|null $first_entry_at
 * @property \Illuminate\Support\Carbon|null $last_entry_at
 * @property \Illuminate\Support\Carbon|null $counting_completed_at
 * @property \Illuminate\Support\Carbon|null $official_results_entered_at
 * @property bool $is_verified
 * @property bool $has_discrepancies
 * @property int|null $verified_by
 * @property-read \App\Models\PollingStation|null $pollingStation
 * @property-read \App\Models\User|null $verifiedBy
 */
class StationSummary extends Model
{
    protected $fillable = [
        'polling_station_id',
        'total_ballots_entered',
        'valid_list_votes',
        'valid_preferential_votes',
        'white_papers',
        'cancelled_papers',
        'first_entry_at',
        'last_entry_at',
        'counting_completed_at',
        'official_results_entered_at',
        'is_verified',
        'has_discrepancies',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    protected $casts = [
        'first_entry_at' => 'datetime',
        'last_entry_at' => 'datetime',
        'counting_completed_at' => 'datetime',
        'official_results_entered_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'has_discrepancies' => 'boolean',
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
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
