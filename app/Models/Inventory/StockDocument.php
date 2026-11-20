<?php

namespace App\Models\Inventory;

use App\Models\SchoolRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stock document: a delivery received into a store, or its opening stock. A draft changes
 * nothing; confirming it writes ledger lines and raises the balances.
 */
class StockDocument extends Model
{
    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_OPENING = 'opening';

    public const TYPE_ISSUE = 'issue';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $table = 'inv_stock_documents';

    protected $fillable = ['number', 'type', 'store_id', 'supplier_id', 'reference', 'document_date', 'notes', 'status', 'created_by', 'request_id', 'received_by'];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class, 'document_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SchoolRequest::class, 'request_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isIssue(): bool
    {
        return $this->type === self::TYPE_ISSUE;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_OPENING => 'Opening stock',
            self::TYPE_ISSUE => 'Issue',
            default => 'Delivery',
        };
    }

    public function totalValue(): float
    {
        return (float) $this->lines->sum(fn ($line) => $line->quantity * $line->unit_cost);
    }
}
