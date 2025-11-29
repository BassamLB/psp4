<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class StationAggregate
 *
 * @property int $id
 * @property int|null $polling_station_id
 * @property int|null $list_id
 * @property int|null $candidate_id
 * @property int|null $vote_count
 * @property \Illuminate\Support\Carbon|null $last_updated_at
 * @property-read \App\Models\PollingStation|null $pollingStation
 * @property-read \App\Models\ElectoralList|null $list
 * @property-read \App\Models\Candidate|null $candidate
 */
class StationAggregate extends Model
{
    protected $fillable = [
        'polling_station_id',
        'list_id',
        'candidate_id',
        'vote_count',
        'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

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
}
