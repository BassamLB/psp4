<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElectoralDistrict extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'total_seats',
        'is_tracked',
    ];

    protected $casts = [
        'is_tracked' => 'boolean',
    ];
}
