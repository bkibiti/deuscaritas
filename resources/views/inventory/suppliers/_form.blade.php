<div class="modal fade" id="inv-supplier-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('inventory.suppliers.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('inventory.suppliers.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New supplier') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control supplier-name" maxlength="150" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TIN</label>
                        <input type="text" name="tin" class="form-control supplier-tin" maxlength="30" value="{{ old('tin') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contact person</label>
                        <input type="text" name="contact_person" class="form-control supplier-contact" maxlength="100" value="{{ old('contact_person') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control supplier-phone" maxlength="30" value="{{ old('phone') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control supplier-email" maxlength="150" value="{{ old('email') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control supplier-address" maxlength="255" value="{{ old('address') }}">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input supplier-active" id="inv-supplier-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="inv-supplier-active">Active</label>
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
            $('#inv-supplier-modal').on('show.bs.modal', function (event) {
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
                $form.find('.supplier-name').val(b.data('name') ?? '');
                $form.find('.supplier-tin').val(b.data('tin') ?? '');
                $form.find('.supplier-contact').val(b.data('contact') ?? '');
                $form.find('.supplier-phone').val(b.data('phone') ?? '');
                $form.find('.supplier-email').val(b.data('email') ?? '');
                $form.find('.supplier-address').val(b.data('address') ?? '');
                $form.find('.supplier-active').prop('checked', b.data('active') === undefined ? true : (b.data('active') === 1 || b.data('active') === '1'));
            });
        });
    </script>
@endpush
