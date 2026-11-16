<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the Deus Caritas schools. Its head is the Head of School step of the approval chain.
 * A school that has departments cannot be deleted, only deactivated.
 */
class School extends Model
{
    protected $table = 'schools';

    protected $fillable = ['name', 'code', 'head_user_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function isInUse(): bool
    {
        return $this->departments()->exists();
    }
}
