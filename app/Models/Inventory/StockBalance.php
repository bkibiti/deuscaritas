<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a store holds of one item right now. Only the stock service changes these rows.
 */
class StockBalance extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'inv_stock_balances';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'value' => 'decimal:2'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function averageCost(): float
    {
        return (float) $this->quantity > 0 ? (float) $this->value / (float) $this->quantity : 0.0;
    }
}
