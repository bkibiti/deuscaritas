<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Reminders module is gone: drop its table and its permissions (from roles and users too).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissions = ['View Reminders', 'Add Reminders', 'Edit Reminders', 'Delete Reminders'];

    public function up(): void
    {
        Schema::dropIfExists('reminders');

        $ids = DB::table('permissions')->whereIn('name', $this->permissions)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to restore.
    }
};
