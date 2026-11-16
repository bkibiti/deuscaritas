<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A department that raises requests. Its head is the Head of Department step of the approval
 * chain. A department with no school serves every school (e.g. ICT, Finance).
 */
class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = ['school_id', 'name', 'head_user_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'head_user_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
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
