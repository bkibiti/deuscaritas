<?php

namespace App\Services;

use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockDocument;
use App\Models\Inventory\Store;
use App\Models\RequestApproval;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the dashboard shows one user. Everyone sees their own requests. Someone who signs
 * requests, or can see all of them, also gets the overview. Stock sections need View Stock.
 * Every figure is read from requests, approvals and the stock ledger, so nothing is typed in.
 */
class DashboardData
{
    public const PERIODS = [
        '6m' => 'Last 6 months',
        'month' => 'This month',
        'year' => 'This year',
    ];

    private const OPEN = [
        SchoolRequest::STATUS_PENDING, SchoolRequest::STATUS_RETURNED,
        SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS,
    ];

    private const FINISHED = [
        SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS, SchoolRequest::STATUS_COMPLETED,
    ];

    private string $period;

    private ?int $schoolId;

    public function __construct(private User $user, ?string $period = null, ?int $schoolId = null)
    {
        $this->period = array_key_exists((string) $period, self::PERIODS) ? $period : '6m';
        // Only head-office staff, who belong to no school, can narrow the figures to one.
        $this->schoolId = $user->school_id === null ? $schoolId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $overview = $this->seesOverview();
        $stock = $this->user->can('View Stock');

        return [
            'overview' => $overview,
            'period' => $this->period,
            'periods' => self::PERIODS,
            'schoolId' => $this->schoolId,
            'canPickSchool' => $this->user->school_id === null,
            'tiles' => $overview ? $this->overviewTiles($stock) : $this->staffTiles(),
            'returned' => SchoolRequest::where('requester_id', $this->user->id)->where('status', SchoolRequest::STATUS_RETURNED)->count(),
            'mine' => $this->myRecentRequests(),
            'assigned' => SchoolRequest::where('assigned_to', $this->user->id)->where('status', SchoolRequest::STATUS_IN_PROGRESS)->with(['requester', 'school', 'department'])->orderBy('assigned_at')->get(),
            'inbox' => $overview ? $this->inbox() : collect(),
            'pipeline' => $overview ? $this->pipeline() : [],
            'months' => $overview ? $this->months() : [],
            'departments' => $overview ? $this->departments() : [],
            'stores' => $stock ? $this->stores() : [],
            'moves' => $stock ? $this->moves() : collect(),
            'stock' => $stock,
        ];
    }

    /**
     * The full dashboard is for people who sign requests (they hold the role of an active
     * step) or can see every request. Others see only their own.
     */
    public function seesOverview(): bool
    {
        return $this->user->can('View All Requests')
            || WorkflowStep::where('is_active', true)->whereIn('role_id', $this->user->roles->pluck('id'))->exists();
    }

    /**
     * Requests the user can see, without drafts and cancelled ones, for one school if chosen.
     *
     * @return Builder<SchoolRequest>
     */
    private function visible()
    {
        return SchoolRequest::visibleTo($this->user)
            ->whereNotIn('status', [SchoolRequest::STATUS_DRAFT, SchoolRequest::STATUS_CANCELLED])
            ->when($this->schoolId, fn ($query, $school) => $query->where('school_id', $school));
    }

    private function since(): Carbon
    {
        $now = now('Africa/Dar_es_Salaam');

        return match ($this->period) {
            'month' => $now->copy()->startOfMonth(),
            'year' => $now->copy()->startOfYear(),
            default => $now->copy()->startOfMonth()->subMonths(5),
        };
    }

    /**
     * @return array<int, array{label: string, value: int, hint: ?string, tone: string, url: ?string}>
     */
    private function overviewTiles(bool $stock): array
    {
        $awaiting = SchoolRequest::awaitingActionBy($this->user);
        $oldest = (clone $awaiting)->with('currentApproval')->get()->map(fn (SchoolRequest $r) => $r->currentApproval?->updated_at ?? $r->submitted_at)->filter()->min();

        $mineOpen = SchoolRequest::where('requester_id', $this->user->id)->whereIn('status', self::OPEN);
        $returned = (clone $mineOpen)->where('status', SchoolRequest::STATUS_RETURNED)->count();

        $tiles = [
            ['label' => 'Waiting for my signature', 'value' => $awaiting->count(), 'hint' => $oldest ? 'Oldest has waited '.$oldest->diffForHumans(null, true) : null, 'tone' => 'warning', 'url' => route('requests.index', ['scope' => 'inbox'])],
            ['label' => 'My open requests', 'value' => $mineOpen->count(), 'hint' => $returned > 0 ? $returned.' returned for changes' : null, 'tone' => 'primary', 'url' => route('requests.index')],
            ['label' => 'Approved, not yet issued', 'value' => $this->visible()->whereIn('status', [SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS])->count(), 'hint' => null, 'tone' => 'success', 'url' => route('requests.index', ['status' => SchoolRequest::STATUS_APPROVED])],
        ];

        if ($stock) {
            $items = StockBalance::where('quantity', '>', 0)->distinct('item_id')->count('item_id');
            $tiles[] = ['label' => 'Items in stock', 'value' => $items, 'hint' => null, 'tone' => 'secondary', 'url' => route('inventory.stock.index')];
        } else {
            $tiles[] = ['label' => 'Completed requests', 'value' => $this->visible()->where('status', SchoolRequest::STATUS_COMPLETED)->count(), 'hint' => null, 'tone' => 'secondary', 'url' => route('requests.index', ['status' => SchoolRequest::STATUS_COMPLETED])];
        }

        return $tiles;
    }

    /**
     * @return array<int, array{label: string, value: int, hint: ?string, tone: string, url: ?string}>
     */
    private function staffTiles(): array
    {
        $mine = fn () => SchoolRequest::where('requester_id', $this->user->id);

        return [
            ['label' => 'Open requests', 'value' => $mine()->whereIn('status', self::OPEN)->count(), 'hint' => null, 'tone' => 'primary', 'url' => route('requests.index')],
            ['label' => 'Returned to me', 'value' => $mine()->where('status', SchoolRequest::STATUS_RETURNED)->count(), 'hint' => null, 'tone' => 'warning', 'url' => route('requests.index', ['status' => SchoolRequest::STATUS_RETURNED])],
            ['label' => 'Approved', 'value' => $mine()->whereIn('status', [SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS])->count(), 'hint' => null, 'tone' => 'success', 'url' => route('requests.index', ['status' => SchoolRequest::STATUS_APPROVED])],
            ['label' => 'Completed', 'value' => $mine()->where('status', SchoolRequest::STATUS_COMPLETED)->count(), 'hint' => null, 'tone' => 'secondary', 'url' => route('requests.index', ['status' => SchoolRequest::STATUS_COMPLETED])],
        ];
    }

    /**
     * The user's own latest requests, each with how many approvals are done.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function myRecentRequests(): Collection
    {
        $steps = max(1, WorkflowStep::where('is_active', true)->count());

        return SchoolRequest::where('requester_id', $this->user->id)
            ->with(['department', 'approvals', 'currentApproval'])
            ->latest('id')->limit(6)->get()
            ->map(function (SchoolRequest $request) use ($steps) {
                $total = $request->approvals->where('status', '!=', RequestApproval::STATUS_SKIPPED)->count() ?: $steps;
                $done = $request->approvals->where('status', RequestApproval::STATUS_APPROVED)->count();

                return [
                    'request' => $request,
                    'done' => $done,
                    'total' => $total,
                    'completeSteps' => in_array($request->status, [SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS, SchoolRequest::STATUS_COMPLETED], true) ? $total : min($done, $total),
                ];
            });
    }

    /**
     * @return Collection<int, SchoolRequest>
     */
    private function inbox(): Collection
    {
        return SchoolRequest::awaitingActionBy($this->user)
            ->with(['requester', 'department', 'school', 'currentApproval'])
            ->orderBy('submitted_at')->limit(5)->get();
    }

    /**
     * Open requests counted at the step they wait at, then approved and returned ones.
     *
     * @return array<int, array{name: string, count: int, tone: string}>
     */
    private function pipeline(): array
    {
        $waiting = RequestApproval::where('status', RequestApproval::STATUS_PENDING)
            ->whereHas('request', fn ($requests) => $requests->whereIn('id', $this->visible()->where('status', SchoolRequest::STATUS_PENDING)->select('requests.id')))
            ->get()->groupBy('step_key')->map->count();

        $rows = WorkflowStep::where('is_active', true)->orderBy('position')->get()
            ->map(fn (WorkflowStep $step) => ['name' => $step->name, 'count' => (int) ($waiting[$step->key] ?? 0), 'tone' => 'primary'])->all();

        $rows[] = ['name' => 'Approved, waiting to issue', 'count' => $this->visible()->whereIn('status', [SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS])->count(), 'tone' => 'success'];
        $rows[] = ['name' => 'Returned for changes', 'count' => $this->visible()->where('status', SchoolRequest::STATUS_RETURNED)->count(), 'tone' => 'warning'];

        return $rows;
    }

    /**
     * Requests raised and fully approved in each month of the period.
     *
     * @return array<int, array{label: string, raised: int, approved: int}>
     */
    private function months(): array
    {
        $start = $this->since();
        $requests = $this->visible()->where(fn ($query) => $query->where('submitted_at', '>=', $start)->orWhere('decided_at', '>=', $start))
            ->get(['id', 'status', 'submitted_at', 'decided_at']);

        $months = [];
        for ($month = $start->copy(); $month->lte(now('Africa/Dar_es_Salaam')); $month->addMonth()) {
            $key = $month->format('Y-m');
            $months[] = [
                'label' => $month->format('M'),
                'raised' => $requests->filter(fn ($r) => $r->submitted_at?->setTimezone('Africa/Dar_es_Salaam')->format('Y-m') === $key)->count(),
                'approved' => $requests->filter(fn ($r) => in_array($r->status, self::FINISHED, true) && $r->decided_at?->setTimezone('Africa/Dar_es_Salaam')->format('Y-m') === $key)->count(),
            ];
        }

        return $months;
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function departments(): array
    {
        return $this->visible()->where('submitted_at', '>=', $this->since())
            ->with('department:id,name')->get(['id', 'department_id'])
            ->groupBy(fn ($r) => $r->department?->name ?? 'Other')
            ->map(fn ($group, $name) => ['name' => $name, 'count' => $group->count()])
            ->sortByDesc('count')->take(6)->values()->all();
    }

    /**
     * @return array<int, array{name: string, items: int}>
     */
    private function stores(): array
    {
        $items = StockBalance::where('quantity', '>', 0)->selectRaw('store_id, count(*) as items')->groupBy('store_id')->pluck('items', 'store_id');

        return Store::where('is_active', true)->orderBy('name')->get()
            ->map(fn (Store $store) => ['name' => $store->name, 'items' => (int) ($items[$store->id] ?? 0)])->all();
    }

    /**
     * @return Collection<int, StockDocument>
     */
    private function moves(): Collection
    {
        return StockDocument::where('status', StockDocument::STATUS_CONFIRMED)
            ->with('store')->withCount('lines')
            ->latest('confirmed_at')->limit(5)->get();
    }
}
