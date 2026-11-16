<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical store (Food, Building Materials). Each item belongs to one store, and the
 * store's keepers are the users who record its stock movements.
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

    public function categories(): HasMany
    {
        return $this->hasMany(ItemCategory::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * A store that has items or categories cannot be deleted, only deactivated.
     */
    public function isInUse(): bool
    {
        return $this->items()->exists() || $this->categories()->exists();
    }
}
