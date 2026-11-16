<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of the chain for one request: who signs it, and what they decided.
 */
class RequestApproval extends Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'request_approvals';

    protected $fillable = [
        'request_id', 'round', 'position', 'step_key', 'step_name', 'approver_id', 'status',
        'activated_at', 'acted_by', 'acted_at', 'comment', 'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'approver_id' => 'integer',
            'activated_at' => 'datetime',
            'acted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SchoolRequest::class, 'request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_WAITING => 'Not reached',
            self::STATUS_PENDING => 'Awaiting action',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_RETURNED => 'Returned for correction',
            self::STATUS_SKIPPED => 'Skipped',
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'text-bg-warning',
            self::STATUS_APPROVED => 'text-bg-success',
            self::STATUS_REJECTED => 'text-bg-danger',
            self::STATUS_RETURNED => 'text-bg-info',
            default => 'text-bg-secondary',
        };
    }
}
