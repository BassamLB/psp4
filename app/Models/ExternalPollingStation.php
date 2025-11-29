<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class ExternalPollingStation
 *
 * @property int $id
 * @property int|null $number
 * @property int|null $city_id
 * @property string|null $mission
 * @property string|null $center_name
 * @property string|null $center_address
 * @property int|null $from_id_number
 * @property int|null $to_id_number
 * @property array<string,mixed>|null $meta
 * @property-read \App\Models\City|null $city
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ElectoralDistrict[] $electoralDistricts
 */
class ExternalPollingStation extends Model
{
    /** @use HasFactory<\Database\Factories\ExternalPollingStationFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'city_id',
        'mission',
        'center_name',
        'center_address',
        'from_id_number',
        'to_id_number',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'from_id_number' => 'integer',
        'to_id_number' => 'integer',
        'city_id' => 'integer',
    ];

    /**
     * Electoral districts that this external polling station serves.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\ElectoralDistrict, $this, \Illuminate\Database\Eloquent\Relations\Pivot, 'pivot'>
     */
    public function electoralDistricts(): BelongsToMany
    {
        return $this->belongsToMany(ElectoralDistrict::class, 'electoral_district_external_polling_station')
            ->withTimestamps();
    }

    /**
     * Check whether the station serves a given district id.
     */
    public function servesDistrict(int $districtId): bool
    {
        return $this->electoralDistricts()->where('electoral_districts.id', $districtId)->exists();
    }

    /**
     * City relation
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
