<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\RequestAttachment;
use App\Models\RequestCategory;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RequestController extends Controller
{
    /**
     * One list, four views of it: the user's own requests, those awaiting their signature,
     * those assigned to them to carry out, and (with permission) every request.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = in_array($request->query('scope'), ['inbox', 'assigned', 'all'], true) ? $request->query('scope') : 'mine';

        abort_if($scope === 'all' && ! $user->can('View All Requests'), 403);

        $request->validate(['status' => ['nullable', Rule::in(array_keys(SchoolRequest::STATUS_LABELS))]]);

        $query = SchoolRequest::with(['requester', 'department', 'category', 'currentApproval'])->latest('id');

        match ($scope) {
            'inbox' => $query->awaitingActionBy($user),
            'assigned' => $query->where('assigned_to', $user->id)->where('status', SchoolRequest::STATUS_IN_PROGRESS),
            'all' => $query->where('status', '!=', SchoolRequest::STATUS_DRAFT),
            default => $query->where('requester_id', $user->id),
        };

        if (! in_array($scope, ['inbox', 'assigned'], true) && $request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return view('requests.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'scope' => $scope,
            'status' => $request->query('status'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('requests.create', [
            'ownSchool' => $request->user()->school,
            'schoolRequest' => new SchoolRequest,
        ] + $this->formOptions());
    }

    public function store(Request $request, RequestWorkflow $workflow): RedirectResponse
    {
        $user = $request->user();

        [$data, $lines] = $this->validated($request, $user);

        $schoolRequest = new SchoolRequest($data);
        $schoolRequest->requester_id = $user->id;
        $schoolRequest->status = SchoolRequest::STATUS_DRAFT;
        $schoolRequest->save();

        $this->saveLines($schoolRequest, $lines);
        $this->storeAttachments($request, $schoolRequest, $user);

        return $this->afterSave($request, $schoolRequest, $workflow);
    }

    public function show(Request $request, SchoolRequest $schoolRequest): View
    {
        $user = $request->user();

        abort_unless($schoolRequest->canBeViewedBy($user), 403);

        $schoolRequest->load([
            'requester', 'department', 'school', 'category', 'assignee', 'attachments.uploader', 'items.item.unit',
            'approvals.actedBy', 'approvals.role', 'currentApproval', 'actions.user',
            'stockDocuments.store', 'stockDocuments.receiver', 'stockDocuments.lines.item.unit',
        ]);

        $canIssue = $schoolRequest->canBeIssuedBy($user);

        return view('requests.show', [
            'schoolRequest' => $schoolRequest,
            'awaitingMe' => $schoolRequest->isAwaiting($user),
            'canIssue' => $canIssue,
            'assignableUsers' => $schoolRequest->canBeAssignedBy($user)
                ? User::where('status', 1)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function edit(Request $request, SchoolRequest $schoolRequest): View
    {
        abort_unless($schoolRequest->canBeEditedBy($request->user()), 403);

        $schoolRequest->load(['attachments', 'items']);

        return view('requests.edit', [
            'ownSchool' => $request->user()->school,
            'schoolRequest' => $schoolRequest,
        ] + $this->formOptions($schoolRequest));
    }

    public function update(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        $user = $request->user();

        abort_unless($schoolRequest->canBeEditedBy($user), 403);

        [$data, $lines] = $this->validated($request, $user, $schoolRequest);

        $schoolRequest->fill($data);
        $schoolRequest->save();

        $this->saveLines($schoolRequest, $lines);

        $this->storeAttachments($request, $schoolRequest, $user);

        return $this->afterSave($request, $schoolRequest, $workflow);
    }

    public function cancel(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        abort_unless($schoolRequest->requester_id === $request->user()->id, 403);

        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $workflow->cancel($schoolRequest, $request->user(), $data['reason'] ?? null);

        session()->flash('alert-success', 'Request cancelled.');

        return redirect()->route('requests.show', $schoolRequest);
    }

    /**
     * Save as a draft, or save and submit. A request that cannot be submitted yet (say, nobody holds a
     * role the chain needs) stays saved as a draft and the reason is shown.
     */
    private function afterSave(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        if ($request->input('action') !== 'submit') {
            session()->flash('alert-success', 'Request saved. It has not been submitted yet.');

            return redirect()->route('requests.show', $schoolRequest);
        }

        try {
            $workflow->submit($schoolRequest, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('requests.edit', $schoolRequest)->withErrors($exception->errors());
        }

        session()->flash('alert-success', 'Request '.$schoolRequest->number.' submitted.');

        return redirect()->route('requests.show', $schoolRequest);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} the request's own fields, then its item lines
     */
    private function validated(Request $request, User $user, ?SchoolRequest $schoolRequest = null): array
    {
        $data = $request->validate([
            'request_category_id' => ['required', 'integer', Rule::exists('request_categories', 'id')->where('is_active', true)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            // Staff raise requests for their own school. Head-office staff have none, so they
            // say which school the request is for. That school's head signs it.
            'school_id' => [
                Rule::requiredIf($user->school_id === null),
                'nullable', 'integer',
                Rule::exists('schools', 'id')->where('is_active', true),
            ],
            'title' => 'required|string|max:150',
            'justification' => 'nullable|string|max:5000',
            'required_date' => 'nullable|date_format:Y-m-d',
            'suggested_priority' => ['nullable', Rule::in(array_keys(SchoolRequest::PRIORITY_LABELS))],
            // Item lines, as on the paper form. Each is an existing, active inventory item.
            'items' => 'nullable|array|max:50',
            'items.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('inv_items', 'id')->where('is_active', true), $this->inStock($schoolRequest)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', $this->withinStock()],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => RequestAttachment::FILE_RULE,
        ], [
            'department_id.required' => 'Choose the department this request is for.',
            'school_id.required' => 'Choose the school this request is for.',
            'items.*.item_id.required' => 'Choose an item on every line, or remove the empty line.',
            'items.*.item_id.exists' => 'Choose items from the list only.',
            'items.*.item_id.in_stock' => 'An item you chose is not in stock. Choose items from the list only.',
            'items.*.item_id.distinct' => 'The same item is on more than one line. Put it on one line with the full quantity.',
            'items.*.quantity.required' => 'Enter a quantity for every item.',
            'items.*.quantity.gt' => 'Each quantity must be more than zero.',
            'attachments.*.max' => 'Each attachment must be 5 MB or smaller.',
            'attachments.*.mimes' => 'Attachments must be PDF, image, Word or Excel files.',
        ]);

        unset($data['attachments']);

        $data['school_id'] = $user->school_id ?? ($data['school_id'] ?? null);

        $lines = $this->lines($data['items'] ?? []);
        unset($data['items'], $data['quantity']);

        return [$data, $lines];
    }

    /**
     * Item lines ready to save: the item and the quantity wanted. Items come from the store, so they carry no price.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $items): array
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $quantity = (float) $item['quantity'];

            $lines[] = [
                'item_id' => (int) $item['item_id'],
                'position' => $index + 1,
                'quantity' => $quantity,
                'unit_price' => null,
                'amount' => null,
            ];
        }

        return $lines;
    }

    /**
     * Replace the request's lines with the ones just submitted.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function saveLines(SchoolRequest $schoolRequest, array $lines): void
    {
        $schoolRequest->items()->delete();
        $schoolRequest->items()->createMany($lines);
    }

    private function storeAttachments(Request $request, SchoolRequest $schoolRequest, User $user): void
    {
        foreach ($request->file('attachments', []) as $file) {
            RequestAttachment::create([
                'request_id' => $schoolRequest->id,
                'uploaded_by' => $user->id,
                'kind' => RequestAttachment::KIND_SUPPORTING,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $file->store('requests/'.$schoolRequest->id, 'local'),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * An item can be requested only while the stores hold some of it. Items already on the
     * request being edited stay allowed, so an old request can still be saved.
     */
    private function inStock(?SchoolRequest $schoolRequest): \Closure
    {
        $kept = $schoolRequest?->exists ? $schoolRequest->items()->pluck('item_id')->all() : [];

        return function (string $attribute, mixed $value, \Closure $fail) use ($kept): void {
            if (in_array((int) $value, $kept, true)) {
                return;
            }

            if ((float) StockBalance::where('item_id', (int) $value)->sum('quantity') <= 0) {
                $fail('An item you chose is not in stock. Choose items from the list only.');
            }
        };
    }

    /**
     * A line cannot ask for more than the stores hold of its item.
     */
    private function withinStock(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_numeric($value)) {
                return;
            }

            $itemId = (int) request()->input(preg_replace('/quantity$/', 'item_id', $attribute));
            if ($itemId === 0) {
                return;
            }

            $available = (float) StockBalance::where('item_id', $itemId)->sum('quantity');
            if ((float) $value > $available + 0.0001) {
                $name = Item::whereKey($itemId)->value('name');
                $fail($name.': only '.rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.').' available in stock, so you cannot ask for '.rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.').'.');
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?SchoolRequest $schoolRequest = null): array
    {
        $kept = $schoolRequest?->exists ? $schoolRequest->items()->pluck('item_id')->all() : [];

        return [
            'categories' => RequestCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'schools' => School::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // The items a line can be, grouped by stock category in the form.
            // Only items the stores hold now, with the quantity available across all stores.
            'itemGroups' => Item::with(['unit', 'category'])
                ->withSum('balances as available', 'quantity')
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->whereIn('id', $kept)
                    ->orWhereHas('balances', fn ($balances) => $balances->where('quantity', '>', 0)))
                ->orderBy('name')
                ->get()
                ->groupBy(fn (Item $item) => $item->category?->name ?? 'Other items')
                ->sortKeys(),
        ];
    }
}
