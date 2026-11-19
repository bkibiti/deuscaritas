<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes every permission the system no longer uses (what was left behind by the Masi code
 * base), from roles and users as well. The permissions in use are listed here: those checked by
 * the routes, menus and screens, and the ones created by the migrations.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $inUse = [
        'View User Management', 'View Users', 'Manage Users', 'View Roles', 'Manage Roles',
        'View Masters', 'View Schools', 'Manage Schools', 'View Departments', 'Manage Departments',
        'View Request Categories', 'Manage Request Categories', 'View Approval Chain', 'Manage Approval Chain',
        'View All Requests', 'Assign Requests',
        'View Inventory Setup', 'Manage Inventory Setup', 'View Inventory Items', 'Manage Inventory Items',
        'View Suppliers', 'Manage Suppliers', 'View Stock', 'Receive Stock',
        'View Reminders', 'Add Reminders', 'Edit Reminders', 'Delete Reminders',
        'View Settings',
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')->whereNotIn('name', $this->inUse)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to restore: these permissions belonged to modules that no longer exist.
    }
};
