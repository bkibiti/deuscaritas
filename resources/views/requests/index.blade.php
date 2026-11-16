@extends("layouts.master")

@php
    $titles = ['mine' => 'My Requests', 'inbox' => 'Awaiting My Action', 'all' => 'All Requests'];
@endphp

@section('content-title')
    {{ $titles[$scope] }}
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Requests / {{ $titles[$scope] }}</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $titles[$scope] }}</h3>
            <div class="card-tools">
                <a href="{{ route('requests.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New request</a>
            </div>
        </div>
        @if ($scope !== 'inbox')
            <div class="card-body border-bottom py-2">
                <form method="GET" action="{{ route('requests.index') }}" class="row g-2 align-items-center">
                    <input type="hidden" name="scope" value="{{ $scope }}">
                    <div class="col-auto">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            @foreach (\App\Models\SchoolRequest::STATUS_LABELS as $value => $label)
                                @continue($scope === 'all' && $value === 'draft')
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        @endif
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Title</th>
                            <th>Category</th>
                            @if ($scope !== 'mine')
                                <th>Requester</th>
                                <th>Department</th>
                            @endif
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $item)
                            <tr>
                                <td style="white-space: nowrap">{{ $item->reference() }}</td>
                                <td>{{ $item->title }}</td>
                                <td>{{ $item->category?->name }}</td>
                                @if ($scope !== 'mine')
                                    <td>{{ $item->requester?->name }}</td>
                                    <td>{{ $item->department?->name }}</td>
                                @endif
                                <td><span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span></td>
                                <td style="white-space: nowrap">{{ $item->submitted_at ? displayTime($item->submitted_at, 'j M Y') : '—' }}</td>
                                <td class="text-end"><a href="{{ route('requests.show', $item) }}" class="btn btn-info btn-sm">Open</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $scope === 'mine' ? 6 : 8 }}" class="text-muted p-3">
                                    {{ $scope === 'inbox' ? 'Nothing is waiting for your action.' : 'No requests to show.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | {{ $titles[$scope] }}");
    </script>
@endpush
