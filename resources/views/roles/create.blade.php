@extends("layouts.master")


@section('page_css')
@endsection

@section('content-title')
   New Role

@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="#"> User Management / Roles / Add Role </a> </li>
@endsection



@section("content")

@php
    // Repopulate ticks after a failed validation (e.g. duplicate name) so the admin doesn't
    // have to re-tick everything. Cast to int so in_array(..., true) compares reliably.
    $selectedPermissions = array_map('intval', (array) old('permissions', []));
@endphp

<div class="col-sm-12">

<div class="card">
            <div class="card-header">
                <h3 class="card-title">Create Role</h3>
            </div>
    <div class="card-body">
        <form action="{{route('roles.store')}}" method="post">
            @csrf()
        <div class="row mb-3">
                <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Role:<font color="red">*</font> </label>
                <div class="col-md-8">
                        <input id="role" type="text" class="form-control" name="name" value="{{old('name')}}" required autofocus>

                </div>
        </div>
        <div class="row mb-3">
            <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Description:<font color="red">*</font> </label>
            <div class="col-md-8">
                    <input id="description" type="text" class="form-control" name="description" value="{{old('description')}}" required>
            </div>
        </div>
        <div class="row mb-3">
            <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Permissions:<font color="red">*</font> </label>
            <div class="col-md-10">
                @include('roles._permissions', ['groups' => \App\Support\PermissionGroups::arrange($permissions)])
                <hr>
                <div class="d-flex justify-content-end gap-2">
                        <a href="{{route('roles.index')}}" class="btn btn-outline-secondary px-4">Back</a>
                        <button type="submit" class="btn btn-primary px-4">Save</button>
                </div>

            </div>
        </div>

        </form>

    </div> {{-- /Card-Body --}}

</div>
</div>

@endsection

@push("page_scripts")
@include('partials.notification')
@endpush
