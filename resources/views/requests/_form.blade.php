{{-- Shared by create and edit. Expects $schoolRequest, $department, $categories, $schools. --}}
<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Department</label>
            <input type="text" class="form-control" value="{{ $department->name }}" disabled>
        </div>
        <div class="col-md-6">
            <label class="form-label">School @if ($department->school_id === null)<span class="text-danger">*</span>@endif</label>
            @if ($department->school_id !== null)
                <input type="text" class="form-control" value="{{ $department->school?->name }}" disabled>
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
            <label class="form-label">Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="4" maxlength="5000" required>{{ old('description', $schoolRequest->description) }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label class="form-label">Reason / justification</label>
            <textarea name="justification" class="form-control @error('justification') is-invalid @enderror" rows="3" maxlength="5000">{{ old('justification', $schoolRequest->justification) }}</textarea>
            @error('justification') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Required by</label>
            <input type="date" name="required_date" class="form-control @error('required_date') is-invalid @enderror" value="{{ old('required_date', $schoolRequest->required_date?->format('Y-m-d')) }}">
            @error('required_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror" min="0" step="any" value="{{ old('quantity', $schoolRequest->quantity === null ? '' : (float) $schoolRequest->quantity) }}">
            @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Estimated cost (TZS)</label>
            <input type="number" name="estimated_cost" class="form-control @error('estimated_cost') is-invalid @enderror" min="0" step="1" value="{{ old('estimated_cost', $schoolRequest->estimated_cost === null ? '' : (float) $schoolRequest->estimated_cost) }}">
            @error('estimated_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
