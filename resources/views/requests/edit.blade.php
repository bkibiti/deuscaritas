@extends("layouts.master")

@section('content-title')
    Edit Request
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Requests / {{ $schoolRequest->reference() }} / Edit</li>
@endsection

@section("content")
<div class="col-lg-9">
    @error('approvers')
        <div class="alert alert-warning">Saved as a draft. {{ $message }}</div>
    @enderror
    @error('status')
        <div class="alert alert-warning">{{ $message }}</div>
    @enderror

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ $schoolRequest->reference() }}</h3></div>
        <form method="POST" action="{{ route('requests.update', $schoolRequest) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('requests._form')
        </form>
    </div>

    @if ($schoolRequest->attachments->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3 class="card-title">Attached files</h3></div>
            <ul class="list-group list-group-flush">
                @foreach ($schoolRequest->attachments as $attachment)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="{{ route('requests.attachments.download', [$schoolRequest, $attachment]) }}">{{ $attachment->original_name }}</a>
                        <form method="POST" action="{{ route('requests.attachments.destroy', [$schoolRequest, $attachment]) }}"
                            onsubmit="return confirm('Remove this file?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Edit Request");
    </script>
@endpush
