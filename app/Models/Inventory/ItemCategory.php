<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stock category (Food Stock, Stationery Stock, Cleaning Materials...), shared by every
 * store and used to organise lists and reports.
 */
class ItemCategory extends Model
{
    protected $table = 'inv_item_categories';

    protected $fillable = ['name'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function isInUse(): bool
    {
        return $this->items()->exists();
    }
}
