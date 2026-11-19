<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedgerEntry extends Model
{
    protected $table = 'inv_stock_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'value' => 'decimal:2',
            'balance_after' => 'decimal:3',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'document_id');
    }
}
