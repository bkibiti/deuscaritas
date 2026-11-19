@extends("layouts.master")

@section('content-title')
    New Request
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Requests / New</li>
@endsection

@section("content")
<div class="col-lg-9">
        <div class="card">
            <div class="card-header"><h3 class="card-title">New request</h3></div>
            <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data">
                @csrf
                @include('requests._form')
            </form>
        </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | New Request");
    </script>
@endpush
