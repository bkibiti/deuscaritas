<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemoveLeftoverPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_permissions_are_removed_everywhere_and_used_ones_stay(): void
    {
        $old = Permission::create(['name' => 'Approve Loans']);
        $keep = Permission::firstOrCreate(['name' => 'View Users', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Staff']);
        $role->givePermissionTo([$old, $keep]);
        $user = User::factory()->create();
        $user->givePermissionTo($old);

        (require database_path('migrations/2026_11_23_000001_remove_leftover_masi_permissions.php'))->up();

        $this->assertNull(Permission::where('name', 'Approve Loans')->first());
        $this->assertSame(['View Users'], $role->fresh()->permissions->pluck('name')->all());
        $this->assertCount(0, $user->fresh()->getDirectPermissions());
        $this->assertNotNull(Permission::where('name', 'View Stock')->first(), 'Permissions in use are untouched.');
    }

    public function test_every_permission_the_code_checks_is_kept(): void
    {
        $before = Permission::pluck('name')->sort()->values()->all();

        (require database_path('migrations/2026_11_23_000001_remove_leftover_masi_permissions.php'))->up();

        $this->assertSame($before, Permission::pluck('name')->sort()->values()->all(), 'The migrations create only permissions the system uses.');
    }
}
