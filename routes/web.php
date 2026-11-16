<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ConfigurationsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RequestCategoryController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsurePasswordIsNotExpired;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root URL goes to the dashboard. Guests are sent to /login by the auth
// middleware, and after login Laravel returns them to /home (not back to the form).
Route::redirect('/', '/home');

Auth::routes(['register' => false]);

Route::middleware(['auth', EnsurePasswordIsNotExpired::class])->group(function () {

    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Forced password change (temporary passwords)
    Route::get('account/force-password', [AccountController::class, 'forcePasswordForm'])->name('account.force-password');
    Route::post('account/force-password', [AccountController::class, 'forcePasswordUpdate'])->name('account.force-password.update');

    // Schools (Masters)
    Route::get('masters/schools', [SchoolController::class, 'index'])->name('schools.index')->middleware('can:View Schools');
    Route::post('masters/schools', [SchoolController::class, 'store'])->name('schools.store')->middleware('can:Manage Schools');
    Route::put('masters/schools/{school}', [SchoolController::class, 'update'])->name('schools.update')->middleware('can:Manage Schools');
    Route::delete('masters/schools/{school}', [SchoolController::class, 'destroy'])->name('schools.destroy')->middleware('can:Manage Schools');

    // Departments (Masters)
    Route::get('masters/departments', [DepartmentController::class, 'index'])->name('departments.index')->middleware('can:View Departments');
    Route::post('masters/departments', [DepartmentController::class, 'store'])->name('departments.store')->middleware('can:Manage Departments');
    Route::put('masters/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update')->middleware('can:Manage Departments');
    Route::delete('masters/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy')->middleware('can:Manage Departments');

    // Request categories (Masters)
    Route::get('masters/request-categories', [RequestCategoryController::class, 'index'])->name('request-categories.index')->middleware('can:View Request Categories');
    Route::post('masters/request-categories', [RequestCategoryController::class, 'store'])->name('request-categories.store')->middleware('can:Manage Request Categories');
    Route::put('masters/request-categories/{requestCategory}', [RequestCategoryController::class, 'update'])->name('request-categories.update')->middleware('can:Manage Request Categories');
    Route::delete('masters/request-categories/{requestCategory}', [RequestCategoryController::class, 'destroy'])->name('request-categories.destroy')->middleware('can:Manage Request Categories');

    // reminder routes
    Route::resource('reminders', ReminderController::class)->middleware('can:View Reminders');

    // Configurations Routes
    Route::get('/configurations', [ConfigurationsController::class, 'index'])->name('configurations.index')->middleware('can:View Settings');
    Route::post('/configurations', [ConfigurationsController::class, 'update'])->name('configurations.update')->middleware('can:View Settings');

    // user roles
    Route::get('user-roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:View Roles');
    Route::get('user-roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('can:Manage Roles');
    Route::post('user-roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:Manage Roles');
    Route::get('user-roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('can:Manage Roles');
    Route::post('user-roles/update', [RoleController::class, 'update'])->name('roles.update')->middleware('can:Manage Roles');
    Route::delete('user-roles/delete', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:Manage Roles');

    // users routes
    Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('can:View Users');
    Route::post('users/register', [UserController::class, 'store'])->name('users.register')->middleware('can:Manage Users');
    Route::post('users/update', [UserController::class, 'update'])->name('users.update')->middleware('can:Manage Users');
    Route::put('users/delete', [UserController::class, 'delete'])->name('users.delete')->middleware('can:Manage Users');
    Route::post('users/de-actiavate', [UserController::class, 'deActivate'])->name('users.deactivate')->middleware('can:Manage Users');
    // Self-service (any authenticated user, their own password/profile only): no permission gate.
    Route::post('users/change-password', [UserController::class, 'changePassword'])->name('change-password');
    Route::get('users/change-password', [UserController::class, 'changePasswordForm'])->name('change-pass-form');
    Route::post('user-profile/update', [UserController::class, 'updateProfile'])->name('update-profile');
    Route::get('users/search', [UserController::class, 'search'])->name('users.search')->middleware('can:View Users');

});
