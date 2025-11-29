<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PollingStDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'polling_station_id',
        'location',
        'from_sijil',
        'to_sijil',
        'sect_id',
        'gender_id',
    ];
}
