<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a store keeps: maize flour in the Food Store, cement in Building Materials.
 * An item belongs to one store and is always counted in one unit.
 */
class Item extends Model
{
    protected $table = 'inv_items';

    protected $fillable = ['store_id', 'item_category_id', 'unit_id', 'code', 'name', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
