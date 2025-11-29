<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Agency
 *
 * @property int $id
 * @property string $name
 * @property int|null $responsible_id
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Delegate[] $delegates
 */
class Agency extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'responsible_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Delegate, $this>
     */
    public function delegates(): HasMany
    {
        return $this->hasMany(Delegate::class);
    }
}
