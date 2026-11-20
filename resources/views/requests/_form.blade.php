{{-- Shared by create and edit. Expects $schoolRequest, $ownSchool, $departments, $categories, $schools, $itemGroups. --}}
<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Department <span class="text-danger">*</span></label>
            <select name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                <option value="">Choose the department this request is for</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id', $schoolRequest->department_id) === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">School @if ($ownSchool === null)<span class="text-danger">*</span>@endif</label>
            @if ($ownSchool !== null)
                <input type="text" class="form-control" value="{{ $ownSchool->name }}" disabled>
            @else
                <select name="school_id" class="form-select @error('school_id') is-invalid @enderror" required>
                    <option value="">Choose the school this request is for</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected((string) old('school_id', $schoolRequest->school_id) === (string) $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
                @error('school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Its Head of School will sign the request.</div>
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label">Category <span class="text-danger">*</span></label>
            <select name="request_category_id" class="form-select @error('request_category_id') is-invalid @enderror" required>
                <option value="">Choose a category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('request_category_id', $schoolRequest->request_category_id) === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('request_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Suggested priority</label>
            <select name="suggested_priority" class="form-select @error('suggested_priority') is-invalid @enderror">
                <option value="">Not set</option>
                @foreach (\App\Models\SchoolRequest::PRIORITY_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('suggested_priority', $schoolRequest->suggested_priority) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('suggested_priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" maxlength="150" required value="{{ old('title', $schoolRequest->title) }}">
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label class="form-label">Reason / justification</label>
            <textarea name="justification" class="form-control @error('justification') is-invalid @enderror" rows="3" maxlength="5000">{{ old('justification', $schoolRequest->justification) }}</textarea>
            @error('justification') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        @php
            // Lines to show: what was just submitted (after a validation error), else the saved ones.
            $lines = old('items');
            if ($lines === null) {
                $lines = $schoolRequest->exists
                    ? $schoolRequest->items->map(fn ($line) => [
                        'item_id' => $line->item_id,
                        'quantity' => (float) $line->quantity,
                    ])->all()
                    : [];
            }
            $lines = array_values((array) $lines);
        @endphp

        <div class="col-12">
            <label class="form-label">Items</label>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-2" id="request-items">
                    <thead>
                        <tr>
                            <th style="width: 3rem">SN</th>
                            <th style="min-width: 15rem">Particular</th>
                            <th style="width: 4.5rem">Unit</th>
                            <th style="width: 6rem" class="text-end">In stock</th>
                            <th style="width: 7rem; min-width: 6rem">Qty</th>
                            <th style="width: 3rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                            @include('requests._item_row', ['index' => $index, 'line' => $line])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="request-items-add"><i class="bi bi-plus-lg"></i> Add item</button>
            @foreach (['items', 'items.*.item_id', 'items.*.quantity'] as $field)
                @error($field) <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            @endforeach
            <div class="form-text">The list shows only items the stores hold now, with the quantity in stock. If an item is missing, it is out of stock or not yet in the catalogue.</div>

            <template id="request-item-template">
                @include('requests._item_row', ['index' => '__INDEX__', 'line' => []])
            </template>
        </div>

        <div class="col-md-6">
            <label class="form-label">Required by</label>
            <input type="date" name="required_date" class="form-control @error('required_date') is-invalid @enderror" value="{{ old('required_date', $schoolRequest->required_date?->format('Y-m-d')) }}">
            @error('required_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
            <label class="form-label">Supporting documents</label>
            <input type="file" name="attachments[]" class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
            @error('attachments') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @error('attachments.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">PDF, image, Word or Excel. Up to 5 MB each.</div>
        </div>
    </div>
</div>
<div class="card-footer d-flex justify-content-end gap-2">
    <a href="{{ $schoolRequest->exists ? route('requests.show', $schoolRequest) : route('requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" name="action" value="draft" class="btn btn-secondary">Save as draft</button>
    <button type="submit" name="action" value="submit" class="btn btn-primary">{{ $schoolRequest->status === 'returned' ? 'Save and send again' : 'Save and submit' }}</button>
</div>

@push('page_scripts')
    <script>
        $(function () {
            var $table = $('#request-items');
            var $body = $table.find('tbody');
            var nextIndex = $body.find('tr').length;

            function format(number) {
                return number.toLocaleString('en-US', { maximumFractionDigits: 2 });
            }

            // Renumber the lines and show each item's unit and the quantity in stock.
            function refresh() {
                $body.find('tr').each(function (position) {
                    var $row = $(this);
                    var $option = $row.find('.request-item-select option:selected');
                    var available = $option.data('available');

                    $row.find('.request-item-sn').text(String(position + 1).padStart(2, '0'));
                    $row.find('.request-item-unit').text($option.data('unit') || '');
                    $row.find('.request-item-available').text(available === undefined || $option.val() === '' ? '' : format(available));

                    // Stop a quantity above what the stores hold.
                    var $quantity = $row.find('.request-item-quantity');
                    var wanted = parseFloat($quantity.val());
                    var tooMuch = available !== undefined && $option.val() !== '' && ! isNaN(wanted) && wanted > available + 0.0001;
                    $quantity.toggleClass('is-invalid', tooMuch);
                    $quantity.attr('max', $option.val() === '' ? null : available);
                    $quantity[0].setCustomValidity(tooMuch ? 'Only ' + format(available) + ' available in stock.' : '');
                });
            }

            $('#request-items-add').on('click', function () {
                var html = $('#request-item-template').html().replace(/__INDEX__/g, nextIndex++);
                $body.append(html);
                refresh();
                $body.find('tr:last .request-item-select').trigger('focus');
            });

            $body.on('click', '.request-item-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            $body.on('change input', '.request-item-select, .request-item-quantity', refresh);

            refresh();
        });
    </script>
@endpush
