@extends("layouts.master")

@section('content-title')
    Request Categories
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Administration / Request Categories</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Request Categories</h3>
            <div class="card-tools">
                @can('Manage Request Categories')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#request-category-modal"
                        data-action="{{ route('request-categories.store') }}" data-method="POST" data-title="New request category">
                        <i class="bi bi-plus-lg"></i> Add category
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
                            <th>Director approval</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>
                                    @if ($category->requires_director)
                                        Always
                                    @elseif ($category->director_cost_threshold !== null)
                                        From TZS {{ formatAmount($category->director_cost_threshold) }}
                                    @else
                                        Not required
                                    @endif
                                </td>
                                <td><span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Request Categories')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#request-category-modal"
                                            data-action="{{ route('request-categories.update', $category) }}" data-method="PUT" data-title="Edit request category"
                                            data-name="{{ $category->name }}"
                                            data-director="{{ $category->requires_director ? 1 : 0 }}" data-threshold="{{ $category->director_cost_threshold }}"
                                            data-active="{{ $category->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('request-categories.destroy', $category) }}" class="d-inline"
                                            onsubmit="return confirm('Delete request category {{ addslashes($category->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted p-3">No request categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('masters.request_categories._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Request Categories");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('request-category-modal')).show(); });
        @endif
    </script>
@endpush
