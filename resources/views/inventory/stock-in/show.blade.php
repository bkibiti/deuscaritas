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
        <div class="card-header"><h3 class="card-title">{{ $document->number }} · {{ $document->typeLabel() }}</h3>
            <div class="card-tools"><span class="badge {{ $document->isDraft() ? 'text-bg-warning' : 'text-bg-success' }}">{{ $document->isDraft() ? 'Draft: stock not yet added' : 'Confirmed' }}</span></div>
        </div>
        <div class="card-body">
            <dl class="row mb-3">
                <dt class="col-sm-3">Store</dt><dd class="col-sm-9">{{ $document->store->name }}</dd>
                <dt class="col-sm-3">Date</dt><dd class="col-sm-9">{{ $document->document_date->format('d M Y') }}</dd>
                @if ($document->supplier) <dt class="col-sm-3">Supplier</dt><dd class="col-sm-9">{{ $document->supplier->name }}</dd> @endif
                <dt class="col-sm-3">Reference</dt><dd class="col-sm-9">{{ $document->reference ?: '—' }}</dd>
                @if ($document->notes) <dt class="col-sm-3">Notes</dt><dd class="col-sm-9">{{ $document->notes }}</dd> @endif
                <dt class="col-sm-3">Recorded by</dt><dd class="col-sm-9">{{ $document->creator->name }}</dd>
                @if ($document->confirmed_at) <dt class="col-sm-3">Confirmed</dt><dd class="col-sm-9">{{ $document->confirmer->name }}, {{ displayTime($document->confirmed_at) }}</dd> @endif
            </dl>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead><tr><th style="width:3rem">SN</th><th>Item</th><th>Unit</th><th class="text-end">Quantity</th><th class="text-end">Unit cost</th><th class="text-end">Amount (TZS)</th></tr></thead>
                    <tbody>
                        @foreach ($document->lines as $line)
                            <tr>
                                <td class="text-center">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $line->item->name }}</td>
                                <td>{{ $line->item->unit?->abbreviation }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }}</td>
                                <td class="text-end">{{ number_format((float) $line->unit_cost, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $line->quantity * (float) $line->unit_cost, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="5" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold">{{ number_format($document->totalValue(), 2) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <a href="{{ route('inventory.stock-in.index') }}" class="btn btn-secondary">Back</a>
            @can('Receive Stock')
                @if ($document->isDraft())
                    <form method="POST" action="{{ route('inventory.stock-in.confirm', $document) }}">@csrf
                        <button class="btn btn-primary">Confirm and add to stock</button></form>
                    <form method="POST" action="{{ route('inventory.stock-in.destroy', $document) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger">Delete draft</button></form>
                @endif
            @endcan
        </div>
    </div>
</div>
@endsection
