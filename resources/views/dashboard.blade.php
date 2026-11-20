@extends("layouts.master")

@section('content-title')
    Dashboard
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@php
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3), '0'), '.');
    $statusTone = [
        'pending' => 'primary', 'returned' => 'warning', 'approved' => 'success', 'in_progress' => 'success',
        'completed' => 'secondary', 'draft' => 'secondary', 'rejected' => 'danger', 'cancelled' => 'secondary',
    ];
    $maxMonth = max(1, collect($months)->flatMap(fn ($m) => [$m['raised'], $m['approved']])->max() ?? 0);
    $maxPipeline = max(1, collect($pipeline)->max('count') ?? 0);
    $maxDepartment = max(1, collect($departments)->max('count') ?? 0);
@endphp

@section("content")
<div class="col-12 mb-1">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="text-body-secondary small">{{ groupName() }} &middot; as at {{ now('Africa/Dar_es_Salaam')->format('l, j M Y') }}</div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @if ($overview)
                <form method="GET" action="{{ route('home') }}" class="d-flex flex-wrap align-items-center gap-2">
                    @if ($canPickSchool)
                        <label for="dashboard-school" class="small text-body-secondary">School</label>
                        <select name="school" id="dashboard-school" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            <option value="">All schools</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}" @selected($schoolId === $school->id)>{{ $school->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <label for="dashboard-period" class="small text-body-secondary">Period</label>
                    <select name="period" id="dashboard-period" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        @foreach ($periods as $key => $label)
                            <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a href="{{ route('requests.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New request</a>
        </div>
    </div>
</div>

@foreach ($tiles as $tile)
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-body-secondary d-flex align-items-center gap-2">
                    <span class="d-inline-block rounded-1 bg-{{ $tile['tone'] }}" style="width: 10px; height: 10px"></span>{{ $tile['label'] }}
                </div>
                <div class="fs-2 fw-bold lh-1 my-2">{{ number_format($tile['value']) }}</div>
                @if ($tile['hint'])
                    <div class="small text-body-secondary">{{ $tile['hint'] }}</div>
                @endif
                @if ($tile['url'])
                    <a href="{{ $tile['url'] }}" class="small stretched-link">Open</a>
                @endif
            </div>
        </div>
    </div>
@endforeach

@if ($returned > 0)
    <div class="col-12">
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0">
            <div>
                <strong>{{ $returned }} {{ \Illuminate\Support\Str::plural('request', $returned) }} {{ $returned === 1 ? 'was' : 'were' }} returned to you.</strong>
                Read the comment, change the request and send it again.
            </div>
            <a href="{{ route('requests.index', ['status' => 'returned']) }}" class="btn btn-sm btn-outline-dark">Show returned requests</a>
        </div>
    </div>
@endif

@if ($assigned->isNotEmpty())
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Assigned to you</h3>
                <a href="{{ route('requests.index', ['scope' => 'assigned']) }}" class="small">Open list</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Request</th><th>Department</th><th>Raised by</th><th>Assigned</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($assigned as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row->title }}</div>
                                    <div class="small text-body-secondary">{{ $row->reference() }} &middot; {{ $row->school?->name }}</div>
                                </td>
                                <td>{{ $row->department?->name }}</td>
                                <td>{{ $row->requester?->name }}</td>
                                <td>{{ $row->assigned_at ? $row->assigned_at->diffForHumans() : '' }}</td>
                                <td class="text-end">@if ($row->canBeIssuedBy(auth()->user()))<a href="{{ route('requests.issue.create', $row) }}" class="btn btn-primary btn-sm">Issue items</a>@else<a href="{{ route('requests.show', $row) }}" class="btn btn-primary btn-sm">Open</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if ($overview)
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Waiting for your signature</h3>
                <a href="{{ route('requests.index', ['scope' => 'inbox']) }}" class="small">Open inbox</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Request</th><th>Department</th><th>Raised by</th><th>Waiting</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($inbox as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row->title }}</div>
                                    <div class="small text-body-secondary">{{ $row->reference() }} &middot; {{ $row->school?->name }}</div>
                                </td>
                                <td>{{ $row->department?->name }}</td>
                                <td>{{ $row->requester?->name }}</td>
                                <td><span class="badge text-bg-warning">{{ ($row->currentApproval?->updated_at ?? $row->submitted_at)?->diffForHumans(null, true) }}</span></td>
                                <td class="text-end"><a href="{{ route('requests.show', $row) }}" class="fw-semibold small">Review</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">Nothing is waiting for your signature.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Where requests are now</h3></div>
            <div class="card-body">
                <p class="small text-body-secondary">Open requests by the step they are waiting at.</p>
                @foreach ($pipeline as $step)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small"><span>{{ $step['name'] }}</span><strong>{{ $step['count'] }}</strong></div>
                        <div class="progress" style="height: 10px" role="img" aria-label="{{ $step['name'] }}: {{ $step['count'] }}">
                            <div class="progress-bar bg-{{ $step['tone'] }}" style="width: {{ round($step['count'] / $maxPipeline * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">Requests each month</h3>
                <div class="small text-body-secondary d-flex gap-3">
                    <span><span class="d-inline-block rounded-1 bg-primary me-1" style="width: 12px; height: 12px"></span>Raised</span>
                    <span><span class="d-inline-block rounded-1 me-1" style="width: 12px; height: 12px; background: #8fd1ac"></span>Fully approved</span>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-end gap-3 border-bottom px-1" style="height: 180px">
                    @foreach ($months as $month)
                        <div class="flex-fill d-flex align-items-end justify-content-center gap-1 h-100" title="{{ $month['label'] }}: {{ $month['raised'] }} raised, {{ $month['approved'] }} fully approved">
                            <div class="bg-primary rounded-top" style="width: 40%; max-width: 34px; height: {{ round($month['raised'] / $maxMonth * 100) }}%; min-height: {{ $month['raised'] > 0 ? 3 : 0 }}px"></div>
                            <div class="rounded-top" style="width: 40%; max-width: 34px; background: #8fd1ac; height: {{ round($month['approved'] / $maxMonth * 100) }}%; min-height: {{ $month['approved'] > 0 ? 3 : 0 }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex gap-3 px-1 small text-body-secondary mt-1">
                    @foreach ($months as $month)
                        <div class="flex-fill text-center">{{ $month['label'] }}<div class="text-body">{{ $month['raised'] }} / {{ $month['approved'] }}</div></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Requests by department</h3></div>
            <div class="card-body">
                @forelse ($departments as $department)
                    <div class="d-flex align-items-center gap-2 mb-3 small">
                        <span class="text-truncate" style="width: 120px">{{ $department['name'] }}</span>
                        <div class="progress flex-fill" style="height: 10px" role="img" aria-label="{{ $department['name'] }}: {{ $department['count'] }}">
                            <div class="progress-bar" style="width: {{ round($department['count'] / $maxDepartment * 100) }}%"></div>
                        </div>
                        <strong style="width: 28px" class="text-end">{{ $department['count'] }}</strong>
                    </div>
                @empty
                    <div class="text-body-secondary">No requests in this period.</div>
                @endforelse
            </div>
        </div>
    </div>
@endif

@if ($stock)
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Stock by store</h3>
                <a href="{{ route('inventory.stock.index') }}" class="small">Stock balances</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($stores as $store)
                    <li class="list-group-item d-flex justify-content-between"><span class="fw-semibold">{{ $store['name'] }}</span><span class="text-body-secondary">{{ number_format($store['items']) }} items in stock</span></li>
                @empty
                    <li class="list-group-item text-body-secondary">No stores yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Recent stock movements</h3>
                <a href="{{ route('inventory.stock-in.index') }}" class="small">All documents</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($moves as $move)
                    <li class="list-group-item d-flex flex-wrap align-items-center gap-3">
                        <span class="badge {{ $move->isIssue() ? 'text-bg-primary' : 'text-bg-success' }}">{{ $move->isIssue() ? 'Issued' : ($move->type === 'opening' ? 'Opening' : 'Stock In') }}</span>
                        <div class="flex-fill">
                            @if ($move->isIssue())
                                <a href="{{ route('requests.show', $move->request_id) }}" class="fw-semibold">{{ $move->number }}</a>
                            @else
                                <a href="{{ route('inventory.stock-in.show', $move) }}" class="fw-semibold">{{ $move->number }}</a>
                            @endif
                            <div class="small text-body-secondary">{{ $move->store?->name }} &middot; {{ $move->lines_count }} {{ \Illuminate\Support\Str::plural('item', $move->lines_count) }} {{ $move->isIssue() ? 'issued' : 'received' }}</div>
                        </div>
                        <span class="small text-body-secondary">{{ $move->confirmed_at ? displayTime($move->confirmed_at) : '' }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">No stock has been received yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endif

<div class="col-12">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">My recent requests</h3>
            <a href="{{ route('requests.index') }}" class="small">All my requests</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Request</th><th>Progress</th><th>Status</th><th>Raised</th><th></th></tr></thead>
                <tbody>
                    @forelse ($mine as $row)
                        @php $request = $row['request']; @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $request->title }}</div>
                                <div class="small text-body-secondary">{{ $request->reference() }} &middot; {{ $request->department?->name }}</div>
                            </td>
                            <td>
                                <div class="d-flex gap-1" role="img" aria-label="{{ $row['done'] }} of {{ $row['total'] }} approvals done">
                                    @for ($i = 0; $i < $row['total']; $i++)
                                        <span class="rounded-pill {{ $i < $row['completeSteps'] ? ($request->status === 'returned' ? 'bg-warning' : 'bg-primary') : 'bg-secondary-subtle' }}" style="width: 26px; height: 8px"></span>
                                    @endfor
                                </div>
                                <div class="small text-body-secondary mt-1">{{ $request->statusLabel() }}</div>
                            </td>
                            <td><span class="badge {{ $request->statusBadgeClass() }}">{{ \App\Models\SchoolRequest::STATUS_LABELS[$request->status] ?? ucfirst($request->status) }}</span></td>
                            <td>{{ ($request->submitted_at ?? $request->created_at)?->setTimezone('Africa/Dar_es_Salaam')->format('j M') }}</td>
                            <td class="text-end">
                                <a href="{{ $request->canBeEditedBy(auth()->user()) ? route('requests.edit', $request) : route('requests.show', $request) }}" class="fw-semibold small">
                                    {{ $request->status === 'returned' ? 'Change and resend' : ($request->status === 'draft' ? 'Continue' : 'View') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">You have not raised a request yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
