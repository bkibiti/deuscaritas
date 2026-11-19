<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A department a request is raised for. A department with no school serves every school
 * (e.g. ICT, Finance).
 */
class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = ['school_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(SchoolRequest::class);
    }

    /**
     * A department that has raised requests cannot be deleted, only deactivated.
     */
    public function isInUse(): bool
    {
        return $this->requests()->exists();
    }
}
