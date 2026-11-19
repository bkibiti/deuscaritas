@extends("layouts.master")

@section('content-title')
    Schools
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Administration / Schools</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Schools</h3>
            <div class="card-tools">
                @can('Manage Schools')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#school-modal"
                        data-action="{{ route('schools.store') }}" data-method="POST" data-title="New school">
                        <i class="bi bi-plus-lg"></i> Add school
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th class="text-end">Staff</th>
                            <th class="text-end">Departments</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($schools as $school)
                            <tr>
                                <td>{{ $school->name }}</td>
                                <td>{{ $school->code ?: '—' }}</td>
                                <td class="text-end">{{ $school->users_count }}</td>
                                <td class="text-end">{{ $school->departments_count }}</td>
                                <td><span class="badge {{ $school->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $school->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Schools')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#school-modal"
                                            data-action="{{ route('schools.update', $school) }}" data-method="PUT" data-title="Edit school"
                                            data-name="{{ $school->name }}" data-code="{{ $school->code }}"
                                            data-active="{{ $school->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        @if ($school->departments_count === 0)
                                            <form method="POST" action="{{ route('schools.destroy', $school) }}" class="d-inline"
                                                onsubmit="return confirm('Delete school {{ addslashes($school->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted p-3">No schools yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.schools._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Schools");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('school-modal')).show(); });
        @endif
    </script>
@endpush
