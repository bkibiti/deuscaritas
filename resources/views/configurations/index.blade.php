@extends("layouts.master")

@section('content-title')
    Settings
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Administration / Settings</li>
@endsection

@section("content")
<div class="col-lg-8">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Group settings</h3>
        </div>

        <form action="{{ route('configurations.update') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="row mb-3">
                    <label class="col-md-4 col-form-label text-md-end">Group name <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input type="text" name="group_name" class="form-control" maxlength="150" required
                            value="{{ old('group_name', $settings['group_name'] ?? '') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 col-form-label text-md-end">Registration number</label>
                    <div class="col-md-8">
                        <input type="text" name="registration_number" class="form-control" maxlength="100"
                            value="{{ old('registration_number', $settings['registration_number'] ?? '') }}">
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 col-form-label text-md-end">Slogan</label>
                    <div class="col-md-8">
                        <textarea name="slogan" class="form-control" rows="2" maxlength="255">{{ old('slogan', $settings['slogan'] ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Settings");
    </script>
@endpush
