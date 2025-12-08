<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voter extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'voters';

    /**
     * Mass-assignable attributes
     *
     * These reflect the common voter fields used across the app and
     * in the factories/controllers.
     *
     * @var string[]
     */
    protected $fillable = [
        'first_name',
        'family_name',
        'father_name',
        'mother_full_name',
        'sijil_number',
        'date_of_birth',
        'gender_id',
        'personal_sect',
        'sect_id',
        'town_id',
        'family_id',
        'belong_id',
        'profession_id',
        'country_id',
        'mobile_number',
        'travelled',
        'deceased',
        'admin_notes',
    ];

    /**
     * Attribute casting
     *
     * @var array<string,string>
     */
    protected $casts = [
        'travelled' => 'boolean',
        'deceased' => 'boolean',
        'date_of_birth' => 'date',
    ];

    /**
     * Relationships
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function sect(): BelongsTo
    {
        return $this->belongsTo(Sect::class);
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function belong(): BelongsTo
    {
        return $this->belongsTo(Belong::class);
    }
    
}
