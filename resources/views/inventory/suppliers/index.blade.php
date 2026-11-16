@extends("layouts.master")

@section('content-title')
    Suppliers
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Suppliers</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Suppliers</h3>
            <div class="card-tools">
                @can('Manage Suppliers')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inv-supplier-modal"
                        data-action="{{ route('inventory.suppliers.store') }}" data-method="POST" data-title="New supplier">
                        <i class="bi bi-plus-lg"></i> Add supplier
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
                            <th>Contact person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>TIN</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr>
                                <td>{{ $supplier->name }}<div class="small text-body-secondary">{{ $supplier->address }}</div></td>
                                <td>{{ $supplier->contact_person ?: '—' }}</td>
                                <td>{{ $supplier->phone ?: '—' }}</td>
                                <td>{{ $supplier->email ?: '—' }}</td>
                                <td>{{ $supplier->tin ?: '—' }}</td>
                                <td><span class="badge {{ $supplier->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Suppliers')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#inv-supplier-modal"
                                            data-action="{{ route('inventory.suppliers.update', $supplier) }}" data-method="PUT" data-title="Edit supplier"
                                            data-name="{{ $supplier->name }}" data-contact="{{ $supplier->contact_person }}" data-phone="{{ $supplier->phone }}"
                                            data-email="{{ $supplier->email }}" data-address="{{ $supplier->address }}" data-tin="{{ $supplier->tin }}"
                                            data-active="{{ $supplier->is_active ? 1 : 0 }}">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('inventory.suppliers.destroy', $supplier) }}" class="d-inline"
                                            onsubmit="return confirm('Delete supplier {{ addslashes($supplier->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No suppliers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('inventory.suppliers._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Suppliers");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('inv-supplier-modal')).show(); });
        @endif
    </script>
@endpush
