{{-- Permission ticks, grouped by area. Expects $groups (title => permissions) and $selectedPermissions (int ids). --}}
<div class="row mb-3">
    <div class="col-sm-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="check_all">
            <label for="check_all" class="form-check-label fw-semibold">Check all</label>
        </div>
    </div>
</div>

<div class="row g-3">
    @foreach ($groups as $title => $members)
        <div class="col-12">
            <div class="card card-outline card-primary mb-0 perm-group">
                <div class="card-header py-2">
                    <div class="form-check mb-0">
                        <input class="form-check-input perm-group-toggle" type="checkbox" id="group_{{ $loop->index }}">
                        <label for="group_{{ $loop->index }}" class="form-check-label fw-semibold">{{ $title }}</label>
                    </div>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        @foreach ($members as $permission)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" id="{{ $permission->id }}" value="{{ $permission->id }}" @checked(in_array((int) $permission->id, $selectedPermissions, true))>
                                    <label for="{{ $permission->id }}" class="form-check-label">{{ $permission->name }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@push('page_scripts')
{{-- Plain JS (no jQuery) so the ticks keep working even if public/build assets drift. --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkAll = document.getElementById('check_all');
    var checkboxes = document.querySelectorAll('.perm-checkbox');

    function sync(boxes, master) {
        var checked = 0;
        boxes.forEach(function (box) { if (box.checked) { checked++; } });
        master.checked = boxes.length > 0 && checked === boxes.length;
        master.indeterminate = checked > 0 && checked < boxes.length;
    }

    function syncAll() {
        sync(checkboxes, checkAll);
        document.querySelectorAll('.perm-group').forEach(function (group) {
            sync(group.querySelectorAll('.perm-checkbox'), group.querySelector('.perm-group-toggle'));
        });
    }

    checkAll.addEventListener('change', function () {
        checkboxes.forEach(function (box) { box.checked = checkAll.checked; });
        syncAll();
    });

    document.querySelectorAll('.perm-group-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            toggle.closest('.perm-group').querySelectorAll('.perm-checkbox').forEach(function (box) { box.checked = toggle.checked; });
            syncAll();
        });
    });

    checkboxes.forEach(function (box) { box.addEventListener('change', syncAll); });

    syncAll();
});
</script>
@endpush
