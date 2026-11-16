<div class="modal fade" id="department-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('departments.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('departments.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New department') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control department-name" maxlength="100" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">School</label>
                        <select name="school_id" class="form-select department-school">
                            <option value="">All schools</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>{{ $school->name }}{{ $school->is_active ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Leave on "All schools" for a department that serves every school.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Head of Department</label>
                        <select name="head_user_id" class="form-select department-head">
                            <option value="">Not set</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('head_user_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input department-active" id="department-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="department-active">Active</label>
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
            $('#department-modal').on('show.bs.modal', function (event) {
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
                $form.find('.department-name').val(b.data('name') ?? '');
                $form.find('.department-school').val(b.data('school') ?? '');
                $form.find('.department-head').val(b.data('head') ?? '');
                $form.find('.department-active').prop('checked', b.data('active') === undefined ? true : (b.data('active') === 1 || b.data('active') === '1'));
            });
        });
    </script>
@endpush
