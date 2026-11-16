<?php

namespace App\Http\Controllers;

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
     * One list, three views of it: the user's own requests, those awaiting their signature,
     * and (with permission) every request.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = in_array($request->query('scope'), ['inbox', 'all'], true) ? $request->query('scope') : 'mine';

        abort_if($scope === 'all' && ! $user->can('View All Requests'), 403);

        $request->validate(['status' => ['nullable', Rule::in(array_keys(SchoolRequest::STATUS_LABELS))]]);

        $query = SchoolRequest::with(['requester', 'department', 'category', 'currentApproval.approver'])->latest('id');

        match ($scope) {
            'inbox' => $query->awaitingActionBy($user),
            'all' => $query->where('status', '!=', SchoolRequest::STATUS_DRAFT),
            default => $query->where('requester_id', $user->id),
        };

        if ($scope !== 'inbox' && $request->filled('status')) {
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
        $user = $request->user()->load('department.school');

        return view('requests.create', [
            'department' => $user->department,
            'schoolRequest' => new SchoolRequest,
        ] + $this->formOptions());
    }

    public function store(Request $request, RequestWorkflow $workflow): RedirectResponse
    {
        $user = $request->user()->load('department');

        if (! $user->department) {
            throw ValidationException::withMessages(['department' => 'You are not linked to a department yet, so you cannot raise a request.']);
        }

        $data = $this->validated($request, $user);

        $schoolRequest = new SchoolRequest($data);
        $schoolRequest->requester_id = $user->id;
        $schoolRequest->department_id = $user->department->id;
        $schoolRequest->status = SchoolRequest::STATUS_DRAFT;
        $schoolRequest->save();

        $this->storeAttachments($request, $schoolRequest, $user);

        return $this->afterSave($request, $schoolRequest, $workflow);
    }

    public function show(Request $request, SchoolRequest $schoolRequest): View
    {
        $user = $request->user();

        abort_unless($schoolRequest->canBeViewedBy($user), 403);

        $schoolRequest->load([
            'requester', 'department', 'school', 'category', 'assignee', 'attachments.uploader',
            'approvals.approver', 'currentApproval', 'actions.user',
        ]);

        return view('requests.show', [
            'schoolRequest' => $schoolRequest,
            'awaitingMe' => $schoolRequest->isAwaiting($user),
            'assignableUsers' => $schoolRequest->canBeAssignedBy($user)
                ? User::where('status', 1)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function edit(Request $request, SchoolRequest $schoolRequest): View
    {
        abort_unless($schoolRequest->canBeEditedBy($request->user()), 403);

        $schoolRequest->load(['department.school', 'attachments']);

        return view('requests.edit', [
            'department' => $schoolRequest->department,
            'schoolRequest' => $schoolRequest,
        ] + $this->formOptions());
    }

    public function update(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        $user = $request->user();

        abort_unless($schoolRequest->canBeEditedBy($user), 403);

        $schoolRequest->load('department');
        $schoolRequest->fill($this->validated($request, $user, $schoolRequest));
        $schoolRequest->save();

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
     * Save as a draft, or save and submit. A request that cannot be submitted yet (say, no
     * Head of Department is set) stays saved as a draft and the reason is shown.
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
     * @return array<string, mixed>
     */
    private function validated(Request $request, User $user, ?SchoolRequest $schoolRequest = null): array
    {
        $department = $schoolRequest?->department ?? $user->department;

        $data = $request->validate([
            'request_category_id' => ['required', 'integer', Rule::exists('request_categories', 'id')->where('is_active', true)],
            // A department that serves every school has no school of its own, so the requester
            // says which school the request is for. That school's head signs it.
            'school_id' => [
                Rule::requiredIf($department->school_id === null),
                'nullable', 'integer',
                Rule::exists('schools', 'id')->where('is_active', true),
            ],
            'title' => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'justification' => 'nullable|string|max:5000',
            'required_date' => 'nullable|date_format:Y-m-d',
            'quantity' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'suggested_priority' => ['nullable', Rule::in(array_keys(SchoolRequest::PRIORITY_LABELS))],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => RequestAttachment::FILE_RULE,
        ], [
            'school_id.required' => 'Choose the school this request is for.',
            'attachments.*.max' => 'Each attachment must be 5 MB or smaller.',
            'attachments.*.mimes' => 'Attachments must be PDF, image, Word or Excel files.',
        ]);

        unset($data['attachments']);

        $data['school_id'] = $department->school_id ?? ($data['school_id'] ?? null);

        return $data;
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
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => RequestCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'schools' => School::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
