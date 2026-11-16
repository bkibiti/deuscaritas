<div class="modal fade" id="inv-store-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('inventory.stores.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('inventory.stores.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New store') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control store-name" maxlength="100" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control store-code" maxlength="20" value="{{ old('code') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control store-description" maxlength="255" value="{{ old('description') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Storekeepers</label>
                        <select name="keepers[]" class="form-select store-keepers" multiple size="6">
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(in_array((string) $user->id, array_map('strval', (array) old('keepers', [])), true))>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">The people who record stock for this store. Hold Ctrl to choose more than one.</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input store-active" id="inv-store-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="inv-store-active">Active</label>
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
            $('#inv-store-modal').on('show.bs.modal', function (event) {
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
                $form.find('.store-name').val(b.data('name') ?? '');
                $form.find('.store-code').val(b.data('code') ?? '');
                $form.find('.store-description').val(b.data('description') ?? '');
                $form.find('.store-keepers').val(String(b.data('keepers') ?? '').split(',').filter(Boolean));
                $form.find('.store-active').prop('checked', b.data('active') === undefined ? true : (b.data('active') === 1 || b.data('active') === '1'));
            });
        });
    </script>
@endpush
