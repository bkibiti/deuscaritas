@extends("layouts.master")

@section('content-title')
    Dashboard
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section("content")
<div class="col-12 mb-3">
    <div class="text-body-secondary small">{{ groupName() }} &middot; as at {{ now('Africa/Dar_es_Salaam')->format('l, j M Y') }}</div>
</div>

@foreach ($counts as $count)
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="text-body-secondary small text-uppercase fw-semibold">{{ $count['label'] }}</div>
                <div class="fs-3 fw-bold">{{ number_format($count['value']) }}</div>
                @if ($count['route'] && auth()->user()->can($count['permission']))
                    <a href="{{ route($count['route']) }}" class="small">Open</a>
                @endif
            </div>
        </div>
    </div>
@endforeach

<div class="col-12">
    <div class="card">
        <div class="card-body text-body-secondary">
            Requests, approvals and the management dashboard will appear here once the request workflow is built.
        </div>
    </div>
</div>
@endsection

@push("page_scripts")
    @include('partials.notification')
@endpush
