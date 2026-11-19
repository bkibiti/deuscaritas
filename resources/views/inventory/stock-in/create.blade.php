@extends("layouts.master")

@section('content-title')
    Stock In
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Inventory / Stock In</li>
@endsection

@section("content")
@php($lines = array_values((array) old('items', [])))
<div class="col-sm-12">
    <form method="POST" action="{{ route('inventory.stock-in.store') }}">
        @csrf
        <div class="card">
            <div class="card-header"><h3 class="card-title">New Stock In</h3></div>
            <div class="card-body">
                @if ($stores->isEmpty())
                    <div class="alert alert-warning">You are not a storekeeper of any store. Ask the administrator to assign you under Inventory / Stores.</div>
                @endif
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select name="type" id="si-type" class="form-select @error('type') is-invalid @enderror">
                            <option value="receipt" @selected(old('type', 'receipt') === 'receipt')>Delivery from supplier</option>
                            <option value="opening" @selected(old('type') === 'opening')>Opening stock</option>
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Store <span class="text-danger">*</span></label>
                        <select name="store_id" class="form-select @error('store_id') is-invalid @enderror" required>
                            @foreach ($stores as $store) <option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->name }}</option> @endforeach
                        </select>
                        @error('store_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3" id="si-supplier-box">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">Choose a supplier</option>
                            @foreach ($suppliers as $supplier) <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option> @endforeach
                        </select>
                        @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="document_date" class="form-control @error('document_date') is-invalid @enderror" max="{{ now('Africa/Dar_es_Salaam')->toDateString() }}" value="{{ old('document_date', now('Africa/Dar_es_Salaam')->toDateString()) }}" required>
                        @error('document_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Delivery note / invoice number</label>
                        <input type="text" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" maxlength="500" value="{{ old('notes') }}">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-2" id="si-items">
                        <thead><tr>
                            <th style="width:3rem">SN</th><th style="min-width:15rem">Item</th><th style="width:4.5rem">Unit</th>
                            <th style="width:8rem">Quantity</th><th style="width:9rem">Unit cost (TZS)</th><th style="width:8rem" class="text-end">Amount</th><th style="width:3rem"></th>
                        </tr></thead>
                        <tbody>@foreach ($lines as $index => $line) @include('inventory.stock-in._row', ['index' => $index, 'line' => $line]) @endforeach</tbody>
                        <tfoot><tr><td colspan="5" class="text-end fw-semibold">Total</td><td class="text-end fw-semibold" id="si-total">0</td><td></td></tr></tfoot>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="si-add"><i class="bi bi-plus-lg"></i> Add item</button>
                @foreach (['items', 'items.*.item_id', 'items.*.quantity', 'items.*.unit_cost'] as $field)
                    @error($field) <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                @endforeach
                <template id="si-template">@include('inventory.stock-in._row', ['index' => '__INDEX__', 'line' => []])</template>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('inventory.stock-in.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="action" value="draft" class="btn btn-outline-primary" @disabled($stores->isEmpty())>Save as draft</button>
                <button type="submit" name="action" value="confirm" class="btn btn-primary" @disabled($stores->isEmpty())>Confirm and add to stock</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('page_scripts')
<script>
    $(function () {
        var $body = $('#si-items tbody');
        var next = $body.find('tr').length;
        function fmt(n) { return n.toLocaleString('en-US', { maximumFractionDigits: 2 }); }
        function refresh() {
            var total = 0;
            $body.find('tr').each(function (i) {
                var $r = $(this), q = parseFloat($r.find('.si-qty').val()), c = parseFloat($r.find('.si-cost').val());
                $r.find('.si-sn').text(String(i + 1).padStart(2, '0'));
                $r.find('.si-unit').text($r.find('.si-item option:selected').data('unit') || '');
                if (!isNaN(q) && !isNaN(c)) { total += q * c; $r.find('.si-amount').text(fmt(q * c)); } else { $r.find('.si-amount').text(''); }
            });
            $('#si-total').text(fmt(total));
        }
        function toggleSupplier() { $('#si-supplier-box').toggle($('#si-type').val() === 'receipt'); }
        $('#si-add').on('click', function () {
            $body.append($('#si-template').html().replace(/__INDEX__/g, next++));
            refresh();
            $body.find('tr:last .si-item').trigger('focus');
        });
        $body.on('click', '.si-remove', function () { $(this).closest('tr').remove(); refresh(); });
        $body.on('input change', 'select, input', refresh);
        $('#si-type').on('change', toggleSupplier);
        if ($body.find('tr').length === 0) { $('#si-add').trigger('click'); }
        toggleSupplier(); refresh();
    });
</script>
@endpush
