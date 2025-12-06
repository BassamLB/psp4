<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoterImportTemp extends Model
{
    protected $table = 'voter_imports_temp';

    protected $fillable = [
        'sijil_number',
        'town_id',
        'first_name',
        'family_name',
        'father_name',
        'mother_full_name',
        'gender_id',
        'sect_id',
        'personal_sect',
        'sijil_additional_string',
        'date_of_birth',
        'processed',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'sijil_number' => 'integer',
        'town_id' => 'integer',
        'gender_id' => 'integer',
        'sect_id' => 'integer',
        'date_of_birth' => 'date',
    ];
}
