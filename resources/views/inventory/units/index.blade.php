@extends("layouts.master")

@section('content-title')
    Units of Measure
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Units</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Units of Measure</h3>
            <div class="card-tools">
                @can('Manage Inventory Setup')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inv-unit-modal"
                        data-action="{{ route('inventory.units.store') }}" data-method="POST" data-title="New unit">
                        <i class="bi bi-plus-lg"></i> Add unit
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
                            <th>Abbreviation</th>
                            <th class="text-end">Items</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($units as $unit)
                            <tr>
                                <td>{{ $unit->name }}</td>
                                <td>{{ $unit->abbreviation }}</td>
                                <td class="text-end">{{ $unit->items_count }}</td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Inventory Setup')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#inv-unit-modal"
                                            data-action="{{ route('inventory.units.update', $unit) }}" data-method="PUT" data-title="Edit unit"
                                            data-name="{{ $unit->name }}" data-abbreviation="{{ $unit->abbreviation }}">
                                            Edit
                                        </button>
                                        @if ($unit->items_count === 0)
                                            <form method="POST" action="{{ route('inventory.units.destroy', $unit) }}" class="d-inline"
                                                onsubmit="return confirm('Delete unit {{ addslashes($unit->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted p-3">No units yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('inventory.units._form')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Units of Measure");

        @if ($errors->any())
            $(function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('inv-unit-modal')).show(); });
        @endif
    </script>
@endpush
