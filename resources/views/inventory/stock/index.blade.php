@extends("layouts.master")

@section('content-title')
    Stock Balances
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Stock Balances</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Stock held</h3>
            <div class="card-tools">
                @can('Receive Stock')
                    <a href="{{ route('inventory.stock-in.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Stock In</a>
                @endcan
            </div>
        </div>
        <div class="card-body border-bottom py-2">
            <form method="GET" action="{{ route('inventory.stock.index') }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <select name="store" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((int) $storeId === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto text-body-secondary small">Shared by Kindergarten, Primary and Secondary.</div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th class="text-end">Quantity</th>
                            <th>Unit</th>
                            <th class="text-end">Average cost</th>
                            <th class="text-end">Value (TZS)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($balances as $balance)
                            @php($low = (float) $balance->quantity <= (float) $balance->item->reorder_level)
                            <tr>
                                <td>{{ $balance->item->name }}
                                    @if ($low) <span class="badge text-bg-warning">{{ (float) $balance->quantity <= 0 ? 'Out of stock' : 'Low' }}</span> @endif
                                </td>
                                <td>{{ $balance->item->category?->name ?: '—' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $balance->quantity, 3), '0'), '.') }}</td>
                                <td>{{ $balance->item->unit?->abbreviation }}</td>
                                <td class="text-end">{{ number_format($balance->averageCost(), 2) }}</td>
                                <td class="text-end">{{ number_format((float) $balance->value, 2) }}</td>
                                <td class="text-end"><a href="{{ route('inventory.stock.card', [$balance->store_id, $balance->item_id]) }}" class="btn btn-info btn-sm">History</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-body-secondary py-4">No stock recorded in this store yet.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($balances->isNotEmpty())
                        <tfoot>
                            <tr><td colspan="5" class="text-end fw-semibold">Total value</td><td class="text-end fw-semibold">{{ number_format($totalValue, 2) }}</td><td></td></tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
