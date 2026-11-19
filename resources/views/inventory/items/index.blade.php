@extends("layouts.master")

@section('content-title')
    Items
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Items</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Items</h3>
            <div class="card-tools">
                @can('Manage Inventory Items')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inv-item-modal"
                        data-action="{{ route('inventory.items.store') }}" data-method="POST" data-title="New item">
                        <i class="bi bi-plus-lg"></i> Add item
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body border-bottom py-2">
            <form method="GET" action="{{ route('inventory.items.index') }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Code</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th class="text-end">Reorder level</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->code ?: '—' }}</td>
                                <td>{{ $item->category?->name ?: '—' }}</td>
                                <td>{{ $item->unit?->abbreviation }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->reorder_level, 3), '0'), '.') }}</td>
                                <td><span class="badge {{ $item->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Inventory Items')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#inv-item-modal"
                                            data-action="{{ route('inventory.items.update', $item) }}" data-method="PUT" data-title="Edit item"
                                            data-name="{{ $item->name }}" data-code="{{ $item->code }}"
                                            data-category="{{ $item->item_category_id }}" data-unit="{{ $item->unit_id }}"
                                            data-reorder="{{ (float) $item->reorder_level }}" data-active="{{ $item->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('inventory.items.destroy', $item) }}" class="d-inline"
                                            onsubmit="return confirm('Delete item {{ addslashes($item->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No items yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('inventory.items._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Items");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('inv-item-modal')).show(); });
        @endif
    </script>
@endpush
