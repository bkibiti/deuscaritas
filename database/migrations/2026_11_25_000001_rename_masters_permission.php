<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Masters menu is now called Administration, and its permission follows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('View Masters', 'View Administration');
    }

    public function down(): void
    {
        $this->rename('View Administration', 'View Masters');
    }

    private function rename(string $from, string $to): void
    {
        if (DB::table('permissions')->where('name', $from)->exists() && ! DB::table('permissions')->where('name', $to)->exists()) {
            DB::table('permissions')->where('name', $from)->update(['name' => $to]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
