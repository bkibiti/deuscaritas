@extends("layouts.master")

@section('content-title')
    Item Categories
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Categories</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Item Categories</h3>
            <div class="card-tools">
                @can('Manage Inventory Setup')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inv-category-modal"
                        data-action="{{ route('inventory.categories.store') }}" data-method="POST" data-title="New category">
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
                            <th class="text-end">Items</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td class="text-end">{{ $category->items_count }}</td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Inventory Setup')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#inv-category-modal"
                                            data-action="{{ route('inventory.categories.update', $category) }}" data-method="PUT" data-title="Edit category"
                                            data-name="{{ $category->name }}">
                                            Edit
                                        </button>
                                        @if ($category->items_count === 0)
                                            <form method="POST" action="{{ route('inventory.categories.destroy', $category) }}" class="d-inline"
                                                onsubmit="return confirm('Delete category {{ addslashes($category->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted p-3">No categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('inventory.categories._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Item Categories");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('inv-category-modal')).show(); });
        @endif
    </script>
@endpush
