<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * One step of the approval chain. The chain is data: the order, which role signs each step
 * (and whether the signer must belong to the request's school) and whether a step is in use
 * can change without touching code.
 */
class WorkflowStep extends Model
{
    /** The signer must belong to the school the request is for. */
    public const SCOPE_SCHOOL = 'school';

    /** A holder of the role signs, wherever they work. */
    public const SCOPE_ANY = 'any';

    public const CONDITION_ALWAYS = 'always';

    public const CONDITION_DIRECTOR_RULE = 'director_rule';

    protected $table = 'workflow_steps';

    protected $fillable = ['position', 'key', 'name', 'role_id', 'scope', 'condition', 'is_active'];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isSchoolScoped(): bool
    {
        return $this->scope === self::SCOPE_SCHOOL;
    }

    public function scopeLabel(): string
    {
        return $this->isSchoolScoped() ? "in the request's school" : 'anywhere';
    }

    /**
     * Whether this step applies to the request at all.
     */
    public function appliesTo(SchoolRequest $request): bool
    {
        if ($this->condition !== self::CONDITION_DIRECTOR_RULE) {
            return true;
        }

        $cost = $request->estimated_cost === null ? null : (float) $request->estimated_cost;

        return $request->category->needsDirector($cost);
    }

    /**
     * The school the signer must belong to for this request, or null when any holder may sign.
     */
    public function schoolFor(SchoolRequest $request): ?int
    {
        return $this->isSchoolScoped() ? $request->school_id : null;
    }

    /**
     * Active users who hold the step's role (in the request's school, for a school step).
     *
     * @return Builder<User>
     */
    public function holdersFor(SchoolRequest $request): Builder
    {
        return User::query()
            ->where('status', 1)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('roles.id', $this->role_id))
            ->when($this->schoolFor($request) !== null, fn (Builder $users) => $users->where('school_id', $this->schoolFor($request)));
    }
}
