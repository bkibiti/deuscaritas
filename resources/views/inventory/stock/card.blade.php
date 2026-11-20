@extends("layouts.master")

@section('content-title')
    Stock Card
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Stock Card</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $item->name }} · {{ $store->name }}</h3>
            <div class="card-tools"><a href="{{ route('inventory.stock.index', ['store' => $store->id]) }}" class="btn btn-secondary btn-sm">Back to balances</a></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th><th>Document</th><th class="text-end">In / (out) {{ $item->unit?->abbreviation }}</th>
                            <th class="text-end">Unit cost</th><th class="text-end">Value (TZS)</th><th class="text-end">Balance {{ $item->unit?->abbreviation }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ $entry->movement_date->format('d M Y') }}</td>
                                <td>
                                    @if ($entry->document->isIssue())
                                        @if ($entry->document->request?->canBeViewedBy(auth()->user()))
                                            <a href="{{ route('requests.show', $entry->document->request_id) }}">{{ $entry->document->number }}</a>
                                        @else
                                            {{ $entry->document->number }}
                                        @endif
                                    @else
                                        <a href="{{ route('inventory.stock-in.show', $entry->document_id) }}">{{ $entry->document->number }}</a>
                                    @endif
                                    <span class="text-body-secondary small">{{ $entry->document->typeLabel() }}</span>
                                </td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $entry->quantity, 3), '0'), '.') }}</td>
                                <td class="text-end">{{ number_format((float) $entry->unit_cost, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $entry->value, 2) }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $entry->balance_after, 3), '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-4">No movements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
