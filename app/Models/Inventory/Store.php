<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical store where stock is kept. Its keepers are the users who record its stock
 * movements. Items are not tied to a store: any store can hold any item.
 */
class Store extends Model
{
    protected $table = 'inv_stores';

    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function keepers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inv_store_keepers', 'store_id', 'user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StockDocument::class);
    }

    /**
     * A store that has stock documents cannot be deleted, only deactivated.
     */
    public function isInUse(): bool
    {
        return $this->documents()->exists();
    }
}
