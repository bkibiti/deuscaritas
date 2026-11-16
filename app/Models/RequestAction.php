<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a request's timeline: what happened, who did it and when.
 */
class RequestAction extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, string> */
    public const LABELS = [
        'submitted' => 'Request submitted',
        'resubmitted' => 'Request sent again after correction',
        'skipped' => 'Step skipped',
        'approved' => 'Approved and signed',
        'rejected' => 'Rejected',
        'returned' => 'Returned for correction',
        'fully_approved' => 'Request approved',
        'assigned' => 'Assigned for implementation',
        'completed' => 'Request completed',
        'cancelled' => 'Request cancelled',
    ];

    protected $table = 'request_actions';

    protected $fillable = ['request_id', 'user_id', 'action', 'step_name', 'note'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SchoolRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        $label = self::LABELS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));

        return $this->step_name ? $this->step_name.': '.$label : $label;
    }
}
