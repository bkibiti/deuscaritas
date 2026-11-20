<?php

namespace App\Models;

use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a request: an inventory item, how many, and (when known) the rate.
 */
class RequestItem extends Model
{
    protected $table = 'request_items';

    protected $fillable = ['request_id', 'item_id', 'position', 'quantity', 'issued_quantity', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'quantity' => 'decimal:3',
            'issued_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SchoolRequest::class, 'request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
