@extends("layouts.master")

@section('content-title')
    Departments
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Administration / Departments</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Departments</h3>
            <div class="card-tools">
                @can('Manage Departments')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#department-modal"
                        data-action="{{ route('departments.store') }}" data-method="POST" data-title="New department">
                        <i class="bi bi-plus-lg"></i> Add department
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
                            <th>School</th>
                            <th class="text-end">Requests</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td>{{ $department->name }}</td>
                                <td>{{ $department->school?->name ?: 'All schools' }}</td>
                                <td class="text-end">{{ $department->requests_count }}</td>
                                <td><span class="badge {{ $department->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Departments')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#department-modal"
                                            data-action="{{ route('departments.update', $department) }}" data-method="PUT" data-title="Edit department"
                                            data-name="{{ $department->name }}" data-school="{{ $department->school_id }}"
                                            data-active="{{ $department->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('departments.destroy', $department) }}" class="d-inline"
                                            onsubmit="return confirm('Delete department {{ addslashes($department->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted p-3">No departments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.departments._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Departments");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('department-modal')).show(); });
        @endif
    </script>
@endpush
