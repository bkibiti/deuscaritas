<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grouping of items within one store (Cereals in the Food Store, Cement in Building
 * Materials), used to organise lists and reports.
 */
class ItemCategory extends Model
{
    protected $table = 'inv_item_categories';

    protected $fillable = ['store_id', 'name'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function isInUse(): bool
    {
        return $this->items()->exists();
    }
}
