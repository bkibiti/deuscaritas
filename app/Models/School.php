<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the Deus Caritas schools. Staff belong to a school; the Head of School is whoever holds
 * that role there. A school that has departments or requests cannot be deleted, only deactivated.
 */
class School extends Model
{
    protected $table = 'schools';

    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function isInUse(): bool
    {
        return $this->departments()->exists() || SchoolRequest::where('school_id', $this->id)->exists();
    }
}
