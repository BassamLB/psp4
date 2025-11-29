<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class City
 *
 * @property int $id
 * @property string $name
 * @property int|null $country_id
 * @property string|null $region
 * @property-read \App\Models\Country|null $country
 */
class City extends Model
{
    /** @use HasFactory<\Database\Factories\CityFactory> */
    use HasFactory;

    protected $fillable = ['name', 'country_id', 'region'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
