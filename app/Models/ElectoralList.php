<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ElectoralList
 *
 * @property int $id
 * @property int|null $election_id
 * @property int|null $electoral_district_id
 * @property string $name
 * @property int|null $number
 * @property string|null $color
 * @property bool $passed_factor
 * @property-read \App\Models\Election|null $election
 * @property-read \App\Models\ElectoralDistrict|null $electoralDistrict
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Candidate[] $candidates
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\BallotEntry[] $ballotEntries
 */
class ElectoralList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'electoral_district_id',
        'name',
        'number',
        'color',
        'passed_factor',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'passed_factor' => 'boolean',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Election, $this>
     */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ElectoralDistrict, $this>
     */
    public function electoralDistrict(): BelongsTo
    {
        return $this->belongsTo(ElectoralDistrict::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'list_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\BallotEntry, $this>
     */
    public function ballotEntries(): HasMany
    {
        return $this->hasMany(BallotEntry::class, 'list_id');
    }
}
