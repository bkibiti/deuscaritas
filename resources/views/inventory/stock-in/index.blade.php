@extends("layouts.master")

@section('content-title')
    Stock In
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Stock In</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Deliveries and opening stock</h3>
            <div class="card-tools">
                @can('Receive Stock')
                    <a href="{{ route('inventory.stock-in.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Stock In</a>
                @endcan
            </div>
        </div>
        <div class="card-body border-bottom py-2">
            <form method="GET" action="{{ route('inventory.stock-in.index') }}" class="row g-2">
                <div class="col-auto">
                    <select name="store" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All stores</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) request('store') === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Number</th><th>Date</th><th>Type</th><th>Store</th><th>Supplier</th><th>Reference</th><th class="text-end">Value (TZS)</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr>
                                <td>{{ $document->number }}</td>
                                <td>{{ $document->document_date->format('d M Y') }}</td>
                                <td>{{ $document->typeLabel() }}</td>
                                <td>{{ $document->store->name }}</td>
                                <td>{{ $document->supplier?->name ?: '—' }}</td>
                                <td>{{ $document->reference ?: '—' }}</td>
                                <td class="text-end">{{ number_format($document->totalValue(), 2) }}</td>
                                <td><span class="badge {{ $document->isDraft() ? 'text-bg-warning' : 'text-bg-success' }}">{{ $document->isDraft() ? 'Draft' : 'Confirmed' }}</span></td>
                                <td class="text-end"><a href="{{ route('inventory.stock-in.show', $document) }}" class="btn btn-info btn-sm">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-body-secondary py-4">Nothing recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($documents->hasPages()) <div class="card-footer">{{ $documents->links() }}</div> @endif
    </div>
</div>
@endsection
