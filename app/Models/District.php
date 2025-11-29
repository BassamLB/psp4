<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class District
 *
 * @property int $id
 * @property string $name
 * @property int|null $electoral_district_id
 * @property array<string,int>|null $sectarian_quotas
 * @property bool $is_tracked
 * @property-read \App\Models\ElectoralDistrict|null $electoralDistrict
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Town[] $towns
 */
class District extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'electoral_district_id',
        'sectarian_quotas',
        'is_tracked',
    ];

    protected $casts = [
        'sectarian_quotas' => 'array',
        'is_tracked' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ElectoralDistrict, $this>
     */
    public function electoralDistrict(): BelongsTo
    {
        return $this->belongsTo(ElectoralDistrict::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Town, $this>
     */
    public function towns(): HasMany
    {
        return $this->hasMany(Town::class);
    }
}
