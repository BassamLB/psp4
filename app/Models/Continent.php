<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Continent extends Model
{
    /** @use HasFactory<\Database\Factories\ContinentFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Country, $this>
     */
    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }
}
