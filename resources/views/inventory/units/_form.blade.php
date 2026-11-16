<div class="modal fade" id="inv-unit-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('inventory.units.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('inventory.units.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New unit') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control unit-name" maxlength="50" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Abbreviation <span class="text-danger">*</span></label>
                        <input type="text" name="abbreviation" class="form-control unit-abbreviation" maxlength="15" required value="{{ old('abbreviation') }}">
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
            $('#inv-unit-modal').on('show.bs.modal', function (event) {
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
                $form.find('.unit-name').val(b.data('name') ?? '');
                $form.find('.unit-abbreviation').val(b.data('abbreviation') ?? '');
            });
        });
    </script>
@endpush
