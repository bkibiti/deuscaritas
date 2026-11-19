<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A request a member of staff raises and the approval chain signs. Named SchoolRequest so it
 * does not clash with Laravel's HTTP Request; the table is `requests`.
 */
class SchoolRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending approval',
        self::STATUS_RETURNED => 'Returned for correction',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /** @var array<string, string> */
    public const PRIORITY_LABELS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    protected $table = 'requests';

    protected $fillable = [
        'requester_id', 'department_id', 'school_id', 'request_category_id', 'title', 'description',
        'justification', 'required_date', 'quantity', 'estimated_cost', 'suggested_priority',
    ];

    protected function casts(): array
    {
        return [
            'requester_id' => 'integer',
            'assigned_to' => 'integer',
            'required_date' => 'date',
            'quantity' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RequestCategory::class, 'request_category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'request_id')->orderBy('round')->orderBy('position');
    }

    /**
     * The step whose turn it is, while the request is pending approval.
     */
    public function currentApproval(): HasOne
    {
        return $this->hasOne(RequestApproval::class, 'request_id')->where('status', RequestApproval::STATUS_PENDING);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RequestAction::class, 'request_id')->orderBy('id');
    }

    /**
     * The request's lines, in the order they were entered.
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'request_id')->orderBy('position')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'request_id')->orderBy('id');
    }

    /**
     * Requests the user may see: their own, those they sign or signed, those assigned to
     * them, or all of them with the "View All Requests" permission.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('View All Requests')) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('requester_id', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->orWhereHas('approvals', function (Builder $approvals) use ($user) {
                    $approvals->where('status', '!=', RequestApproval::STATUS_WAITING)
                        ->where(fn (Builder $mine) => $mine->where('acted_by', $user->id)->orWhere(fn (Builder $role) => static::holdsStep($role, $user)));
                });
        });
    }

    /**
     * Requests waiting for this user's signature right now.
     */
    public function scopeAwaitingActionBy(Builder $query, User $user): void
    {
        $query->where('status', self::STATUS_PENDING)
            ->where('requester_id', '!=', $user->id)
            ->whereHas('currentApproval', fn (Builder $approval) => static::holdsStep($approval, $user));
    }

    /**
     * Narrow approval rows to the steps this user can sign: they hold the step's role and,
     * for a school step, belong to the school it names.
     */
    private static function holdsStep(Builder $approvals, User $user): void
    {
        $approvals->whereIn('role_id', $user->roles->pluck('id'))
            ->where(fn (Builder $scope) => $scope->whereNull('scope_school_id')->orWhere('scope_school_id', $user->school_id));
    }

    public function canBeViewedBy(User $user): bool
    {
        return self::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * Only the requester edits, and only before submission or after it was returned.
     */
    public function canBeEditedBy(User $user): bool
    {
        return $this->requester_id === $user->id
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function canBeCancelledBy(User $user): bool
    {
        return $this->requester_id === $user->id
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_RETURNED], true);
    }

    public function isAwaiting(User $user): bool
    {
        return $this->status === self::STATUS_PENDING && $this->currentApproval?->isActionableBy($user) === true;
    }

    public function canBeAssignedBy(User $user): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_IN_PROGRESS], true)
            && $user->can('Assign Requests');
    }

    public function canBeCompletedBy(User $user): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS
            && ($this->assigned_to === $user->id || $user->can('Assign Requests'));
    }

    /**
     * "Awaiting Head of School" while pending, otherwise the plain status.
     */
    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->currentApproval) {
            return 'Awaiting '.$this->currentApproval->step_name;
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'text-bg-warning',
            self::STATUS_RETURNED => 'text-bg-info',
            self::STATUS_APPROVED, self::STATUS_COMPLETED => 'text-bg-success',
            self::STATUS_REJECTED => 'text-bg-danger',
            self::STATUS_IN_PROGRESS => 'text-bg-primary',
            default => 'text-bg-secondary',
        };
    }

    /**
     * The request number, or "Draft" before the first submission.
     */
    public function reference(): string
    {
        return $this->number ?? 'Draft';
    }

    /**
     * Fingerprint of what an approver signs. A later change to any of these fields gives a
     * different hash from the one stored on the signature.
     */
    public function contentHash(): string
    {
        return hash('sha256', json_encode([
            $this->number,
            $this->requester_id,
            $this->department_id,
            $this->school_id,
            $this->request_category_id,
            $this->title,
            $this->description,
            $this->justification,
            $this->required_date?->toDateString(),
            $this->quantity === null ? null : (string) $this->quantity,
            $this->estimated_cost === null ? null : (string) $this->estimated_cost,
            $this->items()->get()->map(fn (RequestItem $line) => [
                $line->item_id,
                (string) $line->quantity,
                $line->unit_price === null ? null : (string) $line->unit_price,
            ])->all(),
        ]));
    }
}
