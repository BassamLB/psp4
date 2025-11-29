<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Branch
 *
 * @property int $id
 * @property int|null $delegate_id
 * @property string $name
 * @property int|null $responsible_id
 * @property-read \App\Models\Delegate|null $delegate
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Town[] $towns
 */
class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'delegate_id',
        'name',
        'responsible_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Delegate, $this>
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(Delegate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Town, $this>
     */
    public function towns(): HasMany
    {
        return $this->hasMany(Town::class);
    }
}
