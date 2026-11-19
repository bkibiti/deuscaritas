<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * The school this user works at. Head-office staff (Manager, Director, Finance) have none.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Whether the user holds the role and, when a school is given, belongs to that school.
     */
    public function holdsRole(?int $roleId, ?int $schoolId = null): bool
    {
        if ($roleId === null || ($schoolId !== null && $this->school_id !== $schoolId)) {
            return false;
        }

        return $this->roles->contains('id', $roleId);
    }
}
