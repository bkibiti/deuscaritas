<div class="modal fade" id="request-category-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('request-categories.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('request-categories.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New request category') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control category-name" maxlength="100" required value="{{ old('name') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control category-description" rows="2" maxlength="255">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="requires_director" value="1" class="form-check-input category-director" id="request-category-director" @checked(old('requires_director', false))>
                            <label class="form-check-label" for="request-category-director">Director always approves this category</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Director approves from cost (TZS)</label>
                        <input type="number" name="director_cost_threshold" class="form-control category-threshold" min="0" step="1" value="{{ old('director_cost_threshold') }}">
                        <div class="form-text">Leave empty if cost does not decide it.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input category-active" id="request-category-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="request-category-active">Active (shown when making a request)</label>
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
            $('#request-category-modal').on('show.bs.modal', function (event) {
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
                $form.find('.category-name').val(b.data('name') ?? '');
                $form.find('.category-description').val(b.data('description') ?? '');
                $form.find('.category-director').prop('checked', b.data('director') === 1 || b.data('director') === '1');
                $form.find('.category-threshold').val(b.data('threshold') ?? '');
                $form.find('.category-active').prop('checked', b.data('active') === undefined ? true : (b.data('active') === 1 || b.data('active') === '1'));
            });
        });
    </script>
@endpush
