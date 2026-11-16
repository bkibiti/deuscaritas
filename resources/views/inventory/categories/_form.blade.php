<div class="modal fade" id="inv-category-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('inventory.categories.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('inventory.categories.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New category') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Store <span class="text-danger">*</span></label>
                        <select name="store_id" class="form-select category-store" required>
                            <option value="">Choose a store</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->name }}{{ $store->is_active ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control category-name" maxlength="100" required value="{{ old('name') }}">
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
            $('#inv-category-modal').on('show.bs.modal', function (event) {
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
                $form.find('.category-store').val(b.data('store') ?? '');
                $form.find('.category-name').val(b.data('name') ?? '');
            });
        });
    </script>
@endpush
