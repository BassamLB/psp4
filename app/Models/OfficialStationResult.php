<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class OfficialStationResult
 *
 * @property int $id
 * @property int|null $polling_station_id
 * @property int|null $list_id
 * @property int|null $candidate_id
 * @property int|null $official_vote_count
 * @property string|null $document_reference
 * @property int|null $entered_by
 * @property \Illuminate\Support\Carbon|null $entered_at
 * @property int|null $verified_by
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property bool $is_final
 * @property int|null $discrepancy_amount
 * @property string|null $notes
 * @property-read \App\Models\PollingStation|null $pollingStation
 * @property-read \App\Models\ElectoralList|null $list
 * @property-read \App\Models\Candidate|null $candidate
 */
class OfficialStationResult extends Model
{
    protected $fillable = [
        'polling_station_id',
        'list_id',
        'candidate_id',
        'official_vote_count',
        'document_reference',
        'entered_by',
        'entered_at',
        'verified_by',
        'verified_at',
        'is_final',
        'discrepancy_amount',
        'notes',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_final' => 'boolean',
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
