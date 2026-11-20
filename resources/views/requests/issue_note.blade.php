@php
    $fmt = fn ($value) => rtrim(rtrim(number_format((float) $value, 3), '0'), '.') ?: '0';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }} - Stock issue note</title>
    <style>
        @page { size: A4; margin: 16mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #111; margin: 0; background: #eee; }
        .sheet { background: #fff; max-width: 210mm; margin: 16px auto; padding: 18mm 16mm; }
        .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button, .toolbar a { font: inherit; padding: 8px 16px; border: 1px solid #1f6feb; background: #1f6feb; color: #fff; border-radius: 6px; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #fff; color: #333; border-color: #bbb; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 15px; margin: 2px 0 0; letter-spacing: 1px; text-transform: uppercase; color: #444; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 14px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta td:first-child { width: 26%; color: #555; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #333; padding: 6px 8px; }
        table.items th { background: #f0f0f0; text-align: left; }
        .r { text-align: right; }
        .short { margin-top: 10px; padding: 8px 10px; border: 1px solid #b7791f; background: #fffbeb; }
        .sign { display: flex; gap: 30px; margin-top: 56px; }
        .sign div { flex: 1; border-top: 1px solid #111; padding-top: 4px; }
        .sign small { color: #555; display: block; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; padding: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('requests.show', $schoolRequest) }}">Back to request</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <div class="head">
            <div>
                <h1>{{ groupName() }}</h1>
                <h2>Stock issue note</h2>
            </div>
            <div style="text-align:right">
                <strong style="font-size:16px">{{ $document->number }}</strong><br>
                {{ $document->confirmed_at ? $document->confirmed_at->format('d M Y, H:i') : $document->created_at->format('d M Y, H:i') }}
            </div>
        </div>

        <table class="meta">
            <tr><td>Issued from</td><td><strong>{{ $document->store?->name }}</strong></td></tr>
            <tr><td>Request</td><td>{{ $schoolRequest->reference() }} &ndash; {{ $schoolRequest->title }}</td></tr>
            <tr><td>Department / School</td><td>{{ $schoolRequest->department?->name }} / {{ $schoolRequest->school?->name }}</td></tr>
            <tr><td>Requested by</td><td>{{ $schoolRequest->requester?->name }}</td></tr>
            @if ($document->notes)<tr><td>Note</td><td>{{ $document->notes }}</td></tr>@endif
        </table>

        <table class="items">
            <thead><tr><th style="width:36px">SN</th><th>Item</th><th class="r">Asked</th><th class="r">Issued</th><th>Unit</th></tr></thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->item?->name }}</td>
                        <td class="r">{{ isset($asked[$line->item_id]) ? $fmt($asked[$line->item_id]->quantity) : '-' }}</td>
                        <td class="r"><strong>{{ $fmt($line->quantity) }}</strong></td>
                        <td>{{ $line->item?->unit?->abbreviation }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($short->isNotEmpty())
            <div class="short"><strong>Not fully issued:</strong>
                @foreach ($short as $line)
                    {{ $line->item?->name }} ({{ $fmt((float) $asked[$line->item_id]->quantity - (float) $line->quantity) }} {{ $line->item?->unit?->abbreviation }} short){{ $loop->last ? '' : ';' }}
                @endforeach
            </div>
        @endif

        <div class="sign">
            <div><strong>{{ $document->creator?->name }}</strong><small>Issued by (signature &amp; date)</small></div>
            <div><strong>{{ $document->receiver?->name }}</strong><small>Received by (signature &amp; date)</small></div>
        </div>
    </div>
</body>
</html>
