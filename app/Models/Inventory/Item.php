<?php

namespace App\Models\Inventory;

use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the school keeps in stock: maize, A4 paper, cement. An item is registered once
 * for the whole organisation and is always counted in one unit.
 */
class Item extends Model
{
    protected $table = 'inv_items';

    protected $fillable = ['item_category_id', 'unit_id', 'code', 'name', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockLines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class);
    }

    /**
     * An item that appears on a request or a stock document cannot be deleted, only deactivated.
     */
    public function isInUse(): bool
    {
        return $this->requestItems()->exists() || $this->stockLines()->exists();
    }
}
