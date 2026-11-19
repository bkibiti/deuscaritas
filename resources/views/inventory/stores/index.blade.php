@extends("layouts.master")

@section('content-title')
    Stores
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Stores</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Stores</h3>
            <div class="card-tools">
                @can('Manage Inventory Setup')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inv-store-modal"
                        data-action="{{ route('inventory.stores.store') }}" data-method="POST" data-title="New store">
                        <i class="bi bi-plus-lg"></i> Add store
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
                            <th>Storekeepers</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $store)
                            <tr>
                                <td>{{ $store->name }}<div class="small text-body-secondary">{{ $store->description }}</div></td>
                                <td>{{ $store->code ?: '—' }}</td>
                                <td>{{ $store->keepers->pluck('name')->join(', ') ?: '—' }}</td>
                                <td><span class="badge {{ $store->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $store->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Inventory Setup')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#inv-store-modal"
                                            data-action="{{ route('inventory.stores.update', $store) }}" data-method="PUT" data-title="Edit store"
                                            data-name="{{ $store->name }}" data-code="{{ $store->code }}" data-description="{{ $store->description }}"
                                            data-keepers="{{ $store->keepers->pluck('id')->join(',') }}" data-active="{{ $store->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('inventory.stores.destroy', $store) }}" class="d-inline"
                                            onsubmit="return confirm('Delete store {{ addslashes($store->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted p-3">No stores yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('inventory.stores._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Stores");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('inv-store-modal')).show(); });
        @endif
    </script>
@endpush
