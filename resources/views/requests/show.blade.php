@extends("layouts.master")

@php
    $user = auth()->user();
    $latestRound = $schoolRequest->approvals->max('round');
    $canEdit = $schoolRequest->canBeEditedBy($user);
    $canCancel = $schoolRequest->canBeCancelledBy($user);
    $canAssign = $schoolRequest->canBeAssignedBy($user);
    $canComplete = $schoolRequest->canBeCompletedBy($user);
@endphp

@section('content-title')
    {{ $schoolRequest->reference() }}
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Requests / {{ $schoolRequest->reference() }}</li>
@endsection

@section("content")
<div class="col-lg-8">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $schoolRequest->title }}</h3>
            <div class="card-tools">
                <span class="badge {{ $schoolRequest->statusBadgeClass() }}">{{ $schoolRequest->statusLabel() }}</span>
            </div>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">Requested by</dt>
                <dd class="col-sm-8">{{ $schoolRequest->requester?->name }}</dd>
                <dt class="col-sm-4">Department</dt>
                <dd class="col-sm-8">{{ $schoolRequest->department?->name }}</dd>
                <dt class="col-sm-4">School</dt>
                <dd class="col-sm-8">{{ $schoolRequest->school?->name ?: '—' }}</dd>
                <dt class="col-sm-4">Category</dt>
                <dd class="col-sm-8">{{ $schoolRequest->category?->name }}</dd>
                <dt class="col-sm-4">Description</dt>
                <dd class="col-sm-8" style="white-space: pre-line">{{ $schoolRequest->description }}</dd>
                <dt class="col-sm-4">Reason / justification</dt>
                <dd class="col-sm-8" style="white-space: pre-line">{{ $schoolRequest->justification ?: '—' }}</dd>
                <dt class="col-sm-4">Required by</dt>
                <dd class="col-sm-8">{{ $schoolRequest->required_date?->format('j M Y') ?: '—' }}</dd>
                <dt class="col-sm-4">Quantity</dt>
                <dd class="col-sm-8">{{ $schoolRequest->quantity === null ? '—' : rtrim(rtrim(number_format((float) $schoolRequest->quantity, 2), '0'), '.') }}</dd>
                <dt class="col-sm-4">Estimated cost</dt>
                <dd class="col-sm-8">{{ $schoolRequest->estimated_cost === null ? '—' : 'TZS '.formatAmount($schoolRequest->estimated_cost) }}</dd>
                <dt class="col-sm-4">Suggested priority</dt>
                <dd class="col-sm-8">{{ \App\Models\SchoolRequest::PRIORITY_LABELS[$schoolRequest->suggested_priority] ?? '—' }}</dd>
                <dt class="col-sm-4">Submitted</dt>
                <dd class="col-sm-8">{{ $schoolRequest->submitted_at ? displayTime($schoolRequest->submitted_at) : 'Not submitted yet' }}</dd>
                @if ($schoolRequest->assignee)
                    <dt class="col-sm-4">Assigned to</dt>
                    <dd class="col-sm-8">{{ $schoolRequest->assignee->name }} &middot; {{ displayTime($schoolRequest->assigned_at) }}</dd>
                @endif
                @if ($schoolRequest->completed_at)
                    <dt class="col-sm-4">Completed</dt>
                    <dd class="col-sm-8 mb-0">{{ displayTime($schoolRequest->completed_at) }}</dd>
                @endif
            </dl>
        </div>
        @if ($schoolRequest->attachments->isNotEmpty())
            <ul class="list-group list-group-flush">
                @foreach ($schoolRequest->attachments as $attachment)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-paperclip"></i>
                            <a href="{{ route('requests.attachments.download', [$schoolRequest, $attachment]) }}">{{ $attachment->original_name }}</a>
                            <span class="text-body-secondary small">{{ $attachment->readableSize() }}</span>
                        </span>
                        <span class="badge text-bg-light">{{ $attachment->kind === 'completion' ? 'Completion evidence' : 'Supporting document' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($schoolRequest->approvals->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3 class="card-title">Approval chain</h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Step</th>
                                <th>Name</th>
                                <th>Decision</th>
                                <th>Date and time</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schoolRequest->approvals as $approval)
                                @if ($latestRound > 1 && ($loop->first || $approval->round !== $schoolRequest->approvals[$loop->index - 1]->round))
                                    <tr class="table-light"><td colspan="5" class="small text-body-secondary">Round {{ $approval->round }}{{ $approval->round === $latestRound ? ' (current)' : '' }}</td></tr>
                                @endif
                                <tr>
                                    <td>{{ $approval->step_name }}</td>
                                    <td>{{ $approval->approver?->name ?: '—' }}</td>
                                    <td>
                                        @if ($approval->status === 'waiting' && $schoolRequest->status !== 'pending')
                                            <span class="badge text-bg-secondary">Not reached</span>
                                        @else
                                            <span class="badge {{ $approval->statusBadgeClass() }}">{{ $approval->statusLabel() }}</span>
                                        @endif
                                    </td>
                                    <td style="white-space: nowrap">{{ $approval->acted_at ? displayTime($approval->acted_at) : '—' }}</td>
                                    <td>{{ $approval->comment ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($schoolRequest->actions->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3 class="card-title">Timeline</h3></div>
            <ul class="list-group list-group-flush">
                @foreach ($schoolRequest->actions as $action)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between flex-wrap">
                            <strong>{{ $action->label() }}</strong>
                            <span class="text-body-secondary small">{{ displayTime($action->created_at) }}</span>
                        </div>
                        <div class="small text-body-secondary">{{ $action->user?->name }}</div>
                        @if ($action->note)
                            <div class="mt-1" style="white-space: pre-line">{{ $action->note }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

<div class="col-lg-4">
    @error('status')
        <div class="alert alert-warning">{{ $message }}</div>
    @enderror

    @if ($awaitingMe)
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title">Your action: {{ $schoolRequest->currentApproval->step_name }}</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('requests.approve', $schoolRequest) }}" class="mb-4">
                    @csrf
                    <h6>Approve and sign</h6>
                    <textarea name="comment" class="form-control mb-2" rows="2" maxlength="1000" placeholder="Comment (optional)">{{ $errors->approve->any() ? old('comment') : '' }}</textarea>
                    <input type="password" name="password" class="form-control mb-2 @if ($errors->approve->has('password')) is-invalid @endif" placeholder="Your password, to sign" required autocomplete="current-password">
                    @foreach ($errors->approve->all() as $message)
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @endforeach
                    <button type="submit" class="btn btn-success w-100">Approve and sign</button>
                </form>

                <form method="POST" action="{{ route('requests.return', $schoolRequest) }}" class="mb-4">
                    @csrf
                    <h6>Return for correction</h6>
                    <textarea name="comment" class="form-control mb-2 @if ($errors->return->any()) is-invalid @endif" rows="2" maxlength="1000" placeholder="What needs to be corrected?" required>{{ $errors->return->any() ? old('comment') : '' }}</textarea>
                    @foreach ($errors->return->all() as $message)
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @endforeach
                    <button type="submit" class="btn btn-outline-secondary w-100">Return to requester</button>
                </form>

                <form method="POST" action="{{ route('requests.reject', $schoolRequest) }}" onsubmit="return confirm('Reject this request? This ends it.');">
                    @csrf
                    <h6>Reject</h6>
                    <textarea name="comment" class="form-control mb-2 @if ($errors->reject->has('comment')) is-invalid @endif" rows="2" maxlength="1000" placeholder="Reason for rejecting" required>{{ $errors->reject->any() ? old('comment') : '' }}</textarea>
                    <input type="password" name="password" class="form-control mb-2 @if ($errors->reject->has('password')) is-invalid @endif" placeholder="Your password, to sign" required autocomplete="current-password">
                    @foreach ($errors->reject->all() as $message)
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @endforeach
                    <button type="submit" class="btn btn-outline-danger w-100">Reject</button>
                </form>
            </div>
        </div>
    @endif

    @if ($canEdit || $canCancel)
        <div class="card">
            <div class="card-header"><h3 class="card-title">Your request</h3></div>
            <div class="card-body d-grid gap-2">
                @if ($canEdit)
                    <a href="{{ route('requests.edit', $schoolRequest) }}" class="btn btn-primary">
                        {{ $schoolRequest->status === 'returned' ? 'Correct and send again' : 'Edit or submit' }}
                    </a>
                @endif
                @if ($canCancel)
                    <form method="POST" action="{{ route('requests.cancel', $schoolRequest) }}" onsubmit="return confirm('Cancel this request?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">Cancel request</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if ($canAssign)
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ $schoolRequest->assigned_to ? 'Reassign' : 'Assign for implementation' }}</h3></div>
            <form method="POST" action="{{ route('requests.assign', $schoolRequest) }}">
                @csrf
                <div class="card-body">
                    <select name="assigned_to" class="form-select mb-2" required>
                        <option value="">Choose a person</option>
                        @foreach ($assignableUsers as $assignable)
                            <option value="{{ $assignable->id }}" @selected((string) $schoolRequest->assigned_to === (string) $assignable->id)>{{ $assignable->name }}</option>
                        @endforeach
                    </select>
                    <textarea name="note" class="form-control" rows="2" maxlength="1000" placeholder="Note (optional)"></textarea>
                    @foreach ($errors->assign->all() as $message)
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @endforeach
                </div>
                <div class="card-footer text-end"><button type="submit" class="btn btn-primary">Assign</button></div>
            </form>
        </div>
    @endif

    @if ($canComplete)
        <div class="card">
            <div class="card-header"><h3 class="card-title">Mark as completed</h3></div>
            <form method="POST" action="{{ route('requests.complete', $schoolRequest) }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="1000" placeholder="What was done (optional)"></textarea>
                    <input type="file" name="evidence[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <div class="form-text">Evidence, if any. Up to 5 MB each.</div>
                    @foreach ($errors->complete->all() as $message)
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @endforeach
                </div>
                <div class="card-footer text-end"><button type="submit" class="btn btn-success">Mark completed</button></div>
            </form>
        </div>
    @endif
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | {{ $schoolRequest->reference() }}");
    </script>
@endpush
