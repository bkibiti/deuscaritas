<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * A company or person the school receives stock from.
 */
class Supplier extends Model
{
    protected $table = 'inv_suppliers';

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'tin', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
