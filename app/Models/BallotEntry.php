<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BallotEntry
 *
 * @property int $id
 * @property int|null $polling_station_id
 * @property int|null $list_id
 * @property int|null $candidate_id
 * @property string|null $ballot_type
 * @property int|null $entered_by
 * @property \Illuminate\Support\Carbon|null $entered_at
 * @property array<string,mixed>|null $metadata
 * @property-read \App\Models\PollingStation|null $pollingStation
 * @property-read \App\Models\ElectoralList|null $list
 * @property-read \App\Models\Candidate|null $candidate
 * @property-read \App\Models\User|null $enteredBy
 */
class BallotEntry extends Model
{
    protected $fillable = [
        'polling_station_id',
        'list_id',
        'candidate_id',
        'ballot_type',
        'cancellation_reason',
        'entered_by',
        'entered_at',
        'ip_address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\PollingStation, $this>
     */
    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ElectoralList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ElectoralList::class, 'list_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BallotEntry>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\BallotEntry>
     */
    public function scopeValidVotes(Builder $query): Builder
    {
        return $query->whereIn('ballot_type', ['valid_list', 'valid_preferential']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BallotEntry>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\BallotEntry>
     */
    public function scopeByStation(Builder $query, int $stationId): Builder
    {
        return $query->where('polling_station_id', $stationId);
    }

    public function isValid(): bool
    {
        return in_array($this->ballot_type, ['valid_list', 'valid_preferential']);
    }

    public function isPreferential(): bool
    {
        return $this->ballot_type === 'valid_preferential';
    }
}
