<div class="modal fade" id="inv-item-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('inventory.items.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('inventory.items.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New item') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Store <span class="text-danger">*</span></label>
                        <select name="store_id" class="form-select item-store" required>
                            <option value="">Choose a store</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->name }}{{ $store->is_active ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="item_category_id" class="form-select item-category">
                            <option value="">None</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" data-store="{{ $category->store_id }}" @selected((string) old('item_category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control item-name" maxlength="150" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control item-code" maxlength="30" value="{{ old('code') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Unit <span class="text-danger">*</span></label>
                        <select name="unit_id" class="form-select item-unit" required>
                            <option value="">Choose a unit</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reorder level</label>
                        <input type="number" name="reorder_level" class="form-control item-reorder" min="0" step="any" value="{{ old('reorder_level', 0) }}">
                        <div class="form-text">Stock at or below this shows as low.</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input item-active" id="inv-item-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="inv-item-active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

@push('page_scripts')
    <script>
        $(function () {
            // Only the chosen store's categories can be picked.
            function showCategoriesOfStore() {
                var storeId = String($('.item-store').val() || '');
                var $category = $('.item-category');

                $category.find('option[data-store]').each(function () {
                    var match = String($(this).data('store')) === storeId;
                    $(this).prop('hidden', ! match).prop('disabled', ! match);
                });

                if ($category.find('option:selected').prop('disabled')) {
                    $category.val('');
                }
            }

            $('.item-store').on('change', showCategoriesOfStore);
            showCategoriesOfStore();

            $('#inv-item-modal').on('show.bs.modal', function (event) {
                var b = event.relatedTarget ? $(event.relatedTarget) : null;
                var $modal = $(this);
                var $form = $modal.find('form');

                if (! b) {
                    return; // reopened after a validation error: keep submitted values
                }

                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('[name=_method]').val(b.data('method'));
                $modal.find('.modal-title').text(b.data('title'));
                $form.find('.item-store').val(b.data('store') ?? '{{ $storeId }}').trigger('change');
                $form.find('.item-category').val(b.data('category') ?? '');
                $form.find('.item-name').val(b.data('name') ?? '');
                $form.find('.item-code').val(b.data('code') ?? '');
                $form.find('.item-unit').val(b.data('unit') ?? '');
                $form.find('.item-reorder').val(b.data('reorder') ?? 0);
                $form.find('.item-active').prop('checked', b.data('active') === undefined ? true : (b.data('active') === 1 || b.data('active') === '1'));
            });
        });
    </script>
@endpush
