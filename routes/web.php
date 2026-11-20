<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ConfigurationsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Inventory\ItemCategoryController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\StockBalanceController;
use App\Http\Controllers\Inventory\StockInController;
use App\Http\Controllers\Inventory\StoreController as InventoryStoreController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Inventory\UnitController as InventoryUnitController;
use App\Http\Controllers\RequestAttachmentController;
use App\Http\Controllers\RequestCategoryController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RequestDecisionController;
use App\Http\Controllers\RequestFulfilmentController;
use App\Http\Controllers\RequestIssueController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkflowStepController;
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

    // Requests: every logged-in user can raise one. Who may see or act on a request is
    // decided per request (requester, approvers, assignee, or "View All Requests").
    Route::get('requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('requests/create', [RequestController::class, 'create'])->name('requests.create');
    Route::post('requests', [RequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{schoolRequest}', [RequestController::class, 'show'])->name('requests.show');
    Route::get('requests/{schoolRequest}/edit', [RequestController::class, 'edit'])->name('requests.edit');
    Route::put('requests/{schoolRequest}', [RequestController::class, 'update'])->name('requests.update');
    Route::post('requests/{schoolRequest}/cancel', [RequestController::class, 'cancel'])->name('requests.cancel');

    Route::post('requests/{schoolRequest}/approve', [RequestDecisionController::class, 'approve'])->name('requests.approve');
    Route::post('requests/{schoolRequest}/reject', [RequestDecisionController::class, 'reject'])->name('requests.reject');
    Route::post('requests/{schoolRequest}/return', [RequestDecisionController::class, 'returnForCorrection'])->name('requests.return');

    Route::post('requests/{schoolRequest}/assign', [RequestFulfilmentController::class, 'assign'])->name('requests.assign');
    Route::post('requests/{schoolRequest}/complete', [RequestFulfilmentController::class, 'complete'])->name('requests.complete');
    Route::get('requests/{schoolRequest}/issue', [RequestIssueController::class, 'create'])->name('requests.issue.create');
    Route::post('requests/{schoolRequest}/issue', [RequestIssueController::class, 'store'])->name('requests.issue');
    Route::get('requests/{schoolRequest}/issue-notes/{document}', [RequestIssueController::class, 'note'])->name('requests.issue-note');

    Route::get('requests/{schoolRequest}/attachments/{attachment}', [RequestAttachmentController::class, 'download'])->name('requests.attachments.download');
    Route::delete('requests/{schoolRequest}/attachments/{attachment}', [RequestAttachmentController::class, 'destroy'])->name('requests.attachments.destroy');

    // Approval chain (Administration)
    Route::get('masters/approval-chain', [WorkflowStepController::class, 'index'])->name('workflow-steps.index')->middleware('can:View Approval Chain');
    Route::put('masters/approval-chain/{workflowStep}', [WorkflowStepController::class, 'update'])->name('workflow-steps.update')->middleware('can:Manage Approval Chain');

    // Inventory module: set-up (stores, units, categories), items and suppliers
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('stores', [InventoryStoreController::class, 'index'])->name('stores.index')->middleware('can:View Inventory Setup');
        Route::post('stores', [InventoryStoreController::class, 'store'])->name('stores.store')->middleware('can:Manage Inventory Setup');
        Route::put('stores/{store}', [InventoryStoreController::class, 'update'])->name('stores.update')->middleware('can:Manage Inventory Setup');
        Route::delete('stores/{store}', [InventoryStoreController::class, 'destroy'])->name('stores.destroy')->middleware('can:Manage Inventory Setup');

        Route::get('units', [InventoryUnitController::class, 'index'])->name('units.index')->middleware('can:View Inventory Setup');
        Route::post('units', [InventoryUnitController::class, 'store'])->name('units.store')->middleware('can:Manage Inventory Setup');
        Route::put('units/{unit}', [InventoryUnitController::class, 'update'])->name('units.update')->middleware('can:Manage Inventory Setup');
        Route::delete('units/{unit}', [InventoryUnitController::class, 'destroy'])->name('units.destroy')->middleware('can:Manage Inventory Setup');

        Route::get('categories', [ItemCategoryController::class, 'index'])->name('categories.index')->middleware('can:View Inventory Setup');
        Route::post('categories', [ItemCategoryController::class, 'store'])->name('categories.store')->middleware('can:Manage Inventory Setup');
        Route::put('categories/{itemCategory}', [ItemCategoryController::class, 'update'])->name('categories.update')->middleware('can:Manage Inventory Setup');
        Route::delete('categories/{itemCategory}', [ItemCategoryController::class, 'destroy'])->name('categories.destroy')->middleware('can:Manage Inventory Setup');

        Route::get('items', [ItemController::class, 'index'])->name('items.index')->middleware('can:View Inventory Items');
        Route::post('items', [ItemController::class, 'store'])->name('items.store')->middleware('can:Manage Inventory Items');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update')->middleware('can:Manage Inventory Items');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy')->middleware('can:Manage Inventory Items');

        Route::get('stock', [StockBalanceController::class, 'index'])->name('stock.index')->middleware('can:View Stock');
        Route::get('stock/{store}/{item}', [StockBalanceController::class, 'card'])->name('stock.card')->middleware('can:View Stock');

        Route::get('stock-in', [StockInController::class, 'index'])->name('stock-in.index')->middleware('can:View Stock');
        Route::get('stock-in/create', [StockInController::class, 'create'])->name('stock-in.create')->middleware('can:Receive Stock');
        Route::post('stock-in', [StockInController::class, 'store'])->name('stock-in.store')->middleware('can:Receive Stock');
        Route::get('stock-in/{document}', [StockInController::class, 'show'])->name('stock-in.show')->middleware('can:View Stock');
        Route::post('stock-in/{document}/confirm', [StockInController::class, 'confirm'])->name('stock-in.confirm')->middleware('can:Receive Stock');
        Route::delete('stock-in/{document}', [StockInController::class, 'destroy'])->name('stock-in.destroy')->middleware('can:Receive Stock');

        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('can:View Suppliers');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('can:Manage Suppliers');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('can:Manage Suppliers');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('can:Manage Suppliers');
    });

    // Schools (Administration)
    Route::get('masters/schools', [SchoolController::class, 'index'])->name('schools.index')->middleware('can:View Schools');
    Route::post('masters/schools', [SchoolController::class, 'store'])->name('schools.store')->middleware('can:Manage Schools');
    Route::put('masters/schools/{school}', [SchoolController::class, 'update'])->name('schools.update')->middleware('can:Manage Schools');
    Route::delete('masters/schools/{school}', [SchoolController::class, 'destroy'])->name('schools.destroy')->middleware('can:Manage Schools');

    // Departments (Administration)
    Route::get('masters/departments', [DepartmentController::class, 'index'])->name('departments.index')->middleware('can:View Departments');
    Route::post('masters/departments', [DepartmentController::class, 'store'])->name('departments.store')->middleware('can:Manage Departments');
    Route::put('masters/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update')->middleware('can:Manage Departments');
    Route::delete('masters/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy')->middleware('can:Manage Departments');

    // Request categories (Administration)
    Route::get('masters/request-categories', [RequestCategoryController::class, 'index'])->name('request-categories.index')->middleware('can:View Request Categories');
    Route::post('masters/request-categories', [RequestCategoryController::class, 'store'])->name('request-categories.store')->middleware('can:Manage Request Categories');
    Route::put('masters/request-categories/{requestCategory}', [RequestCategoryController::class, 'update'])->name('request-categories.update')->middleware('can:Manage Request Categories');
    Route::delete('masters/request-categories/{requestCategory}', [RequestCategoryController::class, 'destroy'])->name('request-categories.destroy')->middleware('can:Manage Request Categories');

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
