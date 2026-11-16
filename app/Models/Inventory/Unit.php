<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A unit of measure items are counted in (kg, bag, piece).
 */
class Unit extends Model
{
    protected $table = 'inv_units';

    protected $fillable = ['name', 'abbreviation'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function isInUse(): bool
    {
        return $this->items()->exists();
    }
}
