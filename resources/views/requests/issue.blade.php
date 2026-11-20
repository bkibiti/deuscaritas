@extends("layouts.master")

@php
    $fmt = fn ($value) => rtrim(rtrim(number_format((float) $value, 3), '0'), '.') ?: '0';
@endphp

@section('content-title')
    Issue items
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('requests.index', ['scope' => 'assigned']) }}">Assigned to Me</a></li>
    <li class="breadcrumb-item"><a href="{{ route('requests.show', $schoolRequest) }}">{{ $schoolRequest->reference() }}</a></li>
    <li class="breadcrumb-item active">Issue items</li>
@endsection

@section("content")
<div class="col-12">
    <form method="POST" action="{{ route('requests.issue', $schoolRequest) }}" id="issue-form">
        @csrf
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div>
                <h4 class="mb-1">Issue items for &ldquo;{{ $schoolRequest->title }}&rdquo;</h4>
                <div class="text-body-secondary">
                    Raised by {{ $schoolRequest->requester?->name }} &middot; {{ $schoolRequest->department?->name }} &middot; {{ $schoolRequest->school?->name }}
                    @if ($schoolRequest->assigned_at) &middot; assigned to you {{ $schoolRequest->assigned_at->diffForHumans() }} @endif
                </div>
            </div>
            <a href="{{ route('requests.show', $schoolRequest) }}" class="btn btn-outline-secondary">View full request</a>
        </div>

        <ol class="list-unstyled d-flex flex-wrap gap-2 small mb-4">
            @foreach ([['Approved', 'done'], ['Assigned to you', 'done'], ['Issue items', 'now'], ['Completed', 'next']] as [$label, $state])
                <li class="d-flex align-items-center gap-2 rounded-pill px-3 py-1 {{ $state === 'now' ? 'text-bg-primary fw-semibold' : ($state === 'done' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle') }}">
                    <span class="badge rounded-pill {{ $state === 'done' ? 'text-bg-success' : 'text-bg-dark' }}">{{ $state === 'done' ? '✓' : $loop->iteration }}</span>{{ $label }}
                </li>
            @endforeach
        </ol>

        <div class="row g-3">
            <div class="col-lg-8">
                @foreach ($schoolRequest->items as $line)
                    @php
                        $options = $stores[$line->id] ?? collect();
                        $asked = (float) $line->quantity;
                        $default = $options->first();
                        $chosenStore = old('lines.'.$line->id.'.store_id', $default['id'] ?? '');
                        $heldHere = $options->firstWhere('id', (int) $chosenStore)['quantity'] ?? 0;
                        $qty = old('lines.'.$line->id.'.quantity', $default ? min($asked, $default['quantity']) : 0);
                    @endphp
                    <div class="card issue-item mb-3" data-asked="{{ $asked }}" data-name="{{ $line->item?->name }}" data-unit="{{ $line->item?->unit?->abbreviation }}">
                        <div class="card-body pb-2">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fs-5 fw-semibold">{{ $line->item?->name }}</div>
                                    <div class="small text-body-secondary">Asked for <strong class="text-body">{{ $fmt($asked) }} {{ $line->item?->unit?->abbreviation }}</strong></div>
                                </div>
                                <span class="badge rounded-pill issue-chip"></span>
                            </div>

                            @if ($options->isEmpty())
                                <p class="small text-body-secondary mt-3 mb-2">No store holds this item, so nothing can be issued. It stays on the request as not issued.</p>
                                <input type="hidden" class="issue-qty" name="lines[{{ $line->id }}][quantity]" value="0" data-max="0">
                            @else
                                <div class="d-flex flex-wrap align-items-center gap-3 rounded bg-body-tertiary p-3 mt-3">
                                    <div class="flex-grow-1">
                                        @if ($options->count() > 1)
                                            <label class="small text-body-secondary" for="store-{{ $line->id }}">Take from</label>
                                            <select name="lines[{{ $line->id }}][store_id]" id="store-{{ $line->id }}" class="form-select issue-store">
                                                @foreach ($options as $store)
                                                    <option value="{{ $store['id'] }}" data-available="{{ $store['quantity'] }}" @selected((string) $chosenStore === (string) $store['id'])>{{ $store['name'] }} ({{ $fmt($store['quantity']) }} available)</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="fw-medium">{{ $default['name'] }}</div>
                                            <input type="hidden" name="lines[{{ $line->id }}][store_id]" value="{{ $default['id'] }}" class="issue-store" data-available="{{ $default['quantity'] }}">
                                            <div class="small text-body-secondary">{{ $fmt($default['quantity']) }} {{ $line->item?->unit?->abbreviation }} available</div>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="small text-body-secondary d-block" for="qty-{{ $line->id }}">Give ({{ $line->item?->unit?->abbreviation }})</label>
                                        <input type="number" id="qty-{{ $line->id }}" name="lines[{{ $line->id }}][quantity]" class="form-control form-control-lg fw-semibold text-end issue-qty" style="width: 8rem" min="0" step="any" value="{{ $qty }}">
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between small mb-1"><span>Issuing <strong class="issue-given">0</strong> of {{ $fmt($asked) }} {{ $line->item?->unit?->abbreviation }}</span><span class="issue-note fw-semibold"></span></div>
                            <div class="progress" style="height: 8px"><div class="progress-bar issue-bar" style="width: 0"></div></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">Who is receiving the items?</h3></div>
                    <div class="card-body">
                        <label for="received_by" class="form-label">Received by</label>
                        <select name="received_by" id="received_by" class="form-select @if ($errors->issue->has('received_by')) is-invalid @endif" required>
                            <option value="">Choose who received the items</option>
                            @foreach ($receivers as $receiver)
                                <option value="{{ $receiver->id }}" @selected((string) old('received_by', $schoolRequest->requester_id) === (string) $receiver->id)>{{ $receiver->name }}{{ $receiver->id === $schoolRequest->requester_id ? ' (requester)' : '' }}</option>
                            @endforeach
                        </select>
                        <label for="note" class="form-label mt-3">Note <span class="text-body-secondary">(optional)</span></label>
                        <textarea name="note" id="note" class="form-control" rows="2" maxlength="500">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title mb-0">Before you confirm</h3></div>
                    <div class="card-body">
                        <dl class="row mb-2">
                            <dt class="col-8 fw-normal text-body-secondary">Fully issued</dt><dd class="col-4 text-end fw-semibold mb-1" id="sum-full">0</dd>
                            <dt class="col-8 fw-normal text-body-secondary">Short</dt><dd class="col-4 text-end fw-semibold mb-1" id="sum-short">0</dd>
                            <dt class="col-8 fw-normal text-body-secondary">Nothing issued</dt><dd class="col-4 text-end fw-semibold mb-1" id="sum-none">0</dd>
                            <dt class="col-8 fw-normal text-body-secondary">Issue notes created</dt><dd class="col-4 text-end fw-semibold mb-0" id="sum-notes">0</dd>
                        </dl>
                        <div class="alert alert-warning small d-none" id="short-warning"></div>
                        <div class="alert alert-danger small d-none" id="over-warning"></div>

                        <div class="form-check my-3">
                            <input type="checkbox" class="form-check-input @if ($errors->issue->has('confirm')) is-invalid @endif" name="confirm" value="1" id="confirm" @checked(old('confirm'))>
                            <label class="form-check-label small" for="confirm">I understand the stock will be taken out of the stores now and this cannot be undone.</label>
                        </div>

                        @foreach ($errors->issue->all() as $message)
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @endforeach

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="issue-submit" disabled>Issue items and complete</button>
                            <a href="{{ route('requests.show', $schoolRequest) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('page_scripts')
    <script>
        $(function () {
            var $form = $('#issue-form');

            function num(value) {
                var n = parseFloat(value);
                return isNaN(n) ? 0 : n;
            }

            function fmt(n) {
                return (Math.round(n * 1000) / 1000).toLocaleString('en-US', { maximumFractionDigits: 3 });
            }

            // Work out each item's status, the summary and whether the form can be sent.
            function refresh() {
                var full = 0, short = 0, none = 0, over = [], shortages = [], stores = {};

                $('.issue-item').each(function () {
                    var $item = $(this);
                    var asked = num($item.data('asked'));
                    var unit = $item.data('unit');
                    var name = $item.data('name');
                    var $qty = $item.find('.issue-qty');
                    var $store = $item.find('.issue-store');
                    var available = $store.is('select') ? num($store.find('option:selected').data('available')) : num($store.data('available'));
                    var given = num($qty.val());
                    var $chip = $item.find('.issue-chip');
                    var $bar = $item.find('.issue-bar');
                    var $note = $item.find('.issue-note');

                    if ($store.length === 0) {
                        $chip.attr('class', 'badge rounded-pill issue-chip text-bg-secondary').text('Not in stock');
                        $bar.css('width', '0');
                        $note.text('').attr('class', 'issue-note fw-semibold');
                        none++;
                        $item.find('.issue-given').text('0');
                        return;
                    }

                    $qty.attr('max', Math.min(asked, available));
                    $item.find('.issue-given').text(fmt(given));
                    $bar.css('width', Math.min(100, asked > 0 ? given / asked * 100 : 0) + '%');

                    if (given > asked + 0.0001 || given > available + 0.0001) {
                        over.push(name + ': ' + (given > asked ? 'more than was asked for (' + fmt(asked) + ' ' + unit + ')' : 'the store holds only ' + fmt(available) + ' ' + unit));
                        $chip.attr('class', 'badge rounded-pill issue-chip text-bg-danger').text('Too much');
                        $bar.attr('class', 'progress-bar issue-bar bg-danger');
                        $note.text('').attr('class', 'issue-note fw-semibold');
                    } else if (given <= 0) {
                        none++;
                        $chip.attr('class', 'badge rounded-pill issue-chip text-bg-secondary').text('Nothing issued');
                        $bar.attr('class', 'progress-bar issue-bar');
                        $note.text('').attr('class', 'issue-note fw-semibold');
                    } else if (given >= asked - 0.0001) {
                        full++;
                        stores[$store.val()] = true;
                        $chip.attr('class', 'badge rounded-pill issue-chip text-bg-success').text('Complete');
                        $bar.attr('class', 'progress-bar issue-bar bg-success');
                        $note.text('All asked for').attr('class', 'issue-note fw-semibold text-success');
                    } else {
                        short++;
                        stores[$store.val()] = true;
                        shortages.push(name + ' is ' + fmt(asked - given) + ' ' + unit + ' short');
                        $chip.attr('class', 'badge rounded-pill issue-chip text-bg-warning').text('Short by ' + fmt(asked - given) + ' ' + unit);
                        $bar.attr('class', 'progress-bar issue-bar bg-warning');
                        $note.text(available < asked ? 'The store holds only ' + fmt(available) + ' ' + unit : '').attr('class', 'issue-note fw-semibold text-warning-emphasis');
                    }
                });

                $('#sum-full').text(full);
                $('#sum-short').text(short);
                $('#sum-none').text(none);
                $('#sum-notes').text(Object.keys(stores).length);

                $('#short-warning').toggleClass('d-none', shortages.length === 0)
                    .html(shortages.length ? '<strong>' + shortages.join('; ') + '.</strong> The request will still be completed and the shortfall is recorded on it.' : '');
                $('#over-warning').toggleClass('d-none', over.length === 0).text(over.join('. '));

                $('#issue-submit').prop('disabled', ! ($('#confirm').is(':checked') && over.length === 0 && (full + short) > 0 && $('#received_by').val() !== ''));
            }

            // A different store means a different quantity on hand: start from what it can give.
            $form.on('change', '.issue-store', function () {
                var $item = $(this).closest('.issue-item');
                var available = num($(this).find('option:selected').data('available'));
                $item.find('.issue-qty').val(Math.min(num($item.data('asked')), available));
                refresh();
            });

            $form.on('input change', '.issue-qty, #confirm, #received_by', refresh);
            refresh();
        });
    </script>
@endpush
