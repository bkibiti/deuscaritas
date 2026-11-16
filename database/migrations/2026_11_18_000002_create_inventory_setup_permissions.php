<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissionNames = [
        'View Inventory Setup',
        'Manage Inventory Setup',
        'View Inventory Items',
        'Manage Inventory Items',
        'View Suppliers',
        'Manage Suppliers',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->permissionNames as $name) {
            if (! DB::table('permissions')->where('name', $name)->exists()) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $permissionIds = DB::table('permissions')->whereIn('name', $this->permissionNames)->pluck('id');
        $superuserId = DB::table('roles')->where('name', 'Superuser')->value('id');

        if ($superuserId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $superuserId, 'permission_id' => $permissionId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', $this->permissionNames)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
