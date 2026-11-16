<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of the approval chain. The chain is data: the order, who signs each step and
 * whether a step is in use can change without touching code.
 */
class WorkflowStep extends Model
{
    public const APPROVER_DEPARTMENT_HEAD = 'department_head';

    public const APPROVER_SCHOOL_HEAD = 'school_head';

    public const APPROVER_USER = 'user';

    public const CONDITION_ALWAYS = 'always';

    public const CONDITION_DIRECTOR_RULE = 'director_rule';

    protected $table = 'workflow_steps';

    protected $fillable = ['position', 'key', 'name', 'approver_type', 'user_id', 'condition', 'is_active'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether one named user signs this step (rather than a department or school head).
     */
    public function hasNamedApprover(): bool
    {
        return $this->approver_type === self::APPROVER_USER;
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
     * The user who signs this step for the request, or null when nobody is set.
     */
    public function approverIdFor(SchoolRequest $request): ?int
    {
        return match ($this->approver_type) {
            self::APPROVER_DEPARTMENT_HEAD => $request->department?->head_user_id,
            self::APPROVER_SCHOOL_HEAD => $request->school?->head_user_id,
            default => $this->user_id,
        };
    }
}
