<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Town
 *
 * @property int $id
 * @property string $name
 * @property int|null $district_id
 * @property int|null $branch_id
 * @property-read \App\Models\District|null $district
 * @property-read \App\Models\Branch|null $branch
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PollingStation[] $pollingStations
 */
class Town extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'district_id', 'branch_id'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\PollingStation, $this>
     */
    public function pollingStations(): HasMany
    {
        return $this->hasMany(PollingStation::class);
    }
}
