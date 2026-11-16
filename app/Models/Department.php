<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
