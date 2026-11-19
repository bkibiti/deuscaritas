@extends("layouts.master")

@section('content-title')
    Approval Chain
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Administration / Approval Chain</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Approval chain, in signing order</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Step</th>
                            <th>Role that signs</th>
                            <th>When</th>
                            <th>In use</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($steps as $step)
                            <tr>
                                <td>{{ $step->position }}</td>
                                <td>{{ $step->name }}</td>
                                <td>
                                    @can('Manage Approval Chain')
                                        <select name="role_id" form="workflow-step-{{ $step->id }}" class="form-select form-select-sm" style="min-width: 220px">
                                            <option value="">Not set</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" @selected($step->role_id === $role->id)>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        {{ $step->role?->name ?: 'Not set' }}
                                    @endcan
                                    @php($people = $holders->filter(fn ($user) => $user->roles->contains('id', $step->role_id)))
                                    <div class="small text-body-secondary mt-1">
                                        Signed by a holder of this role {{ $step->scopeLabel() }}.
                                        @if ($step->role_id)
                                            Now: {{ $people->isEmpty() ? 'nobody' : $people->map(fn ($user) => $user->name.($step->isSchoolScoped() ? ' ('.($user->school?->name ?: 'no school').')' : ''))->implode(', ') }}.
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $step->condition === 'director_rule' ? 'Only when the category or the cost requires it' : 'Every request' }}</td>
                                <td>
                                    @can('Manage Approval Chain')
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="is_active" value="1" form="workflow-step-{{ $step->id }}" class="form-check-input" @checked($step->is_active)>
                                        </div>
                                    @else
                                        <span class="badge {{ $step->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $step->is_active ? 'Yes' : 'No' }}</span>
                                    @endcan
                                </td>
                                <td class="text-end">
                                    @can('Manage Approval Chain')
                                        <form method="POST" action="{{ route('workflow-steps.update', $step) }}" id="workflow-step-{{ $step->id }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-body-secondary small">
            A request cannot be submitted while a step it needs has nobody holding its role. Give people the role under Users. Anyone holding the role can sign, and nobody signs their own request.
        </div>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Approval Chain");
    </script>
@endpush
