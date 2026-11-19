@extends("layouts.master")


@section('page_css')
@endsection

@section('content-title')
   Update Role

@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="#"> User Management / Roles / Update Role </a> </li>
@endsection



@section("content")

@php
    // old('permissions') wins after a failed validation (e.g. duplicate name), so the admin's
    // ticks aren't lost; otherwise fall back to what the role already has. Both sides are cast
    // to int so in_array(..., true) compares reliably regardless of DB driver string/int quirks.
    $selectedPermissions = old('permissions') !== null
        ? array_map('intval', (array) old('permissions'))
        : $rolePermissions;
@endphp

<div class="col-sm-12">

<div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Role</h3>
            </div>
    <div class="card-body">
        <form action="{{route('roles.update')}}" method="post">
            @csrf()
            <input type="hidden" name="id" value="{{$role->id}}">

        <div class="row mb-3">
                <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Role:<font color="red">*</font> </label>
                <div class="col-md-8">
                        <input id="role" type="text" class="form-control" name="name" value="{{$role->name}}" required autofocus>

                </div>
        </div>
        <div class="row mb-3">
            <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Description:<font color="red">*</font> </label>
            <div class="col-md-8">
                    <input id="description" type="text" class="form-control" name="description" value="{{$role->description}}" required>
            </div>
        </div>
        <div class="row mb-3">
            <label style="text-align: right;" class="col-md-2 col-form-label text-md-end">Permissions:<font color="red">*</font> </label>
            <div class="col-md-10">
                @include('roles._permissions', ['groups' => \App\Support\PermissionGroups::arrange($permissionsAll)])
                <hr>
                <div class="d-flex justify-content-end gap-2">
                        <a href="{{route('roles.index')}}" class="btn btn-outline-secondary px-4">Back</a>
                        <button type="submit" class="btn btn-primary px-4">Update</button>
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
