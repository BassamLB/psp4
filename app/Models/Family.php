<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    use HasFactory;

    protected $table = 'families';

    protected $guarded = [];

    /**
     * The voters that belong to this family.
     */
    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class, 'family_id');
    }

    /**
     * Father reference (a voter)
     */
    public function father(): BelongsTo
    {
        return $this->belongsTo(Voter::class, 'father_id');
    }

    /**
     * Mother reference (a voter)
     */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(Voter::class, 'mother_id');
    }

    /**
     * Town relation
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class, 'town_id');
    }

    /**
     * Sect relation
     */
    public function sect(): BelongsTo
    {
        return $this->belongsTo(Sect::class, 'sect_id');
    }
}
