<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PollingStation
 *
 * @property int $id
 * @property int|null $town_id
 * @property int|null $election_id
 * @property int|null $station_number
 * @property string|null $location
 * @property int|null $registered_voters
 * @property bool $is_open
 * @property bool $is_on_hold
 * @property bool $is_closed
 * @property bool $is_done
 * @property bool $is_checked
 * @property bool $is_final
 * @property-read \App\Models\Town|null $town
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\BallotEntry[] $ballotEntries
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StationAggregate[] $stationAggregates
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\OfficialStationResult[] $officialResults
 * @property-read \App\Models\StationSummary|null $summary
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StationUserAssignment[] $userAssignments
 */
class PollingStation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'town_id',
        'station_number',
        'location',
        'registered_voters',
        'white_papers_count',
        'cancelled_papers_count',
        'voters_count',
        'is_open',
        'is_on_hold',
        'is_closed',
        'is_done',
        'is_checked',
        'is_final',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'is_on_hold' => 'boolean',
        'is_closed' => 'boolean',
        'is_done' => 'boolean',
        'is_checked' => 'boolean',
        'is_final' => 'boolean',
    ];

    // Relationships
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Town, $this>
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\BallotEntry, $this>
     */
    public function ballotEntries()
    {
        return $this->hasMany(BallotEntry::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\StationAggregate, $this>
     */
    public function stationAggregates()
    {
        return $this->hasMany(StationAggregate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\OfficialStationResult, $this>
     */
    public function officialResults()
    {
        return $this->hasMany(OfficialStationResult::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\StationSummary, $this>
     */
    public function summary()
    {
        return $this->hasOne(StationSummary::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\StationUserAssignment, $this>
     */
    public function userAssignments()
    {
        return $this->hasMany(StationUserAssignment::class);
    }
}
