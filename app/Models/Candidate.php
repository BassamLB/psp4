<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Candidate
 *
 * @property int $id
 * @property int|null $list_id
 * @property string $full_name
 * @property int|null $position_on_list
 * @property int|null $sect_id
 * @property string|null $voter_id
 * @property string|null $party_affiliation
 * @property string|null $photo_url
 * @property bool $withdrawn
 * @property \Illuminate\Support\Carbon|null $withdrawn_at
 * @property int|null $preferential_votes_count
 * @property-read \App\Models\ElectoralList|null $electoralList
 * @property-read \App\Models\Sect|null $sect
 */
class Candidate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'list_id',
        'full_name',
        'position_on_list',
        'sect_id',
        'voter_id',
        'party_affiliation',
        'photo_url',
        'withdrawn',
        'withdrawn_at',
        'preferential_votes_count',
    ];

    protected function casts(): array
    {
        return [
            'withdrawn' => 'boolean',
            'withdrawn_at' => 'date',
            'preferential_votes_count' => 'integer',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ElectoralList, $this>
     */
    public function electoralList(): BelongsTo
    {
        return $this->belongsTo(ElectoralList::class, 'list_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Sect, $this>
     */
    public function sect(): BelongsTo
    {
        return $this->belongsTo(Sect::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\BallotEntry, $this>
     */
    public function ballotEntries(): HasMany
    {
        return $this->hasMany(BallotEntry::class, 'candidate_id');
    }
}
