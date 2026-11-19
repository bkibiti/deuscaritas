<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RestoreSuperuserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_the_role_its_permissions_and_the_users_membership(): void
    {
        $user = User::factory()->create(['username' => 'beatus']);
        $role = Role::where('name', 'Superuser')->first() ?? Role::create(['name' => 'Superuser']);
        $role->syncPermissions([]);
        $user->syncRoles([]);

        $this->artisan('dcs:restore-superuser', ['username' => 'beatus'])->assertSuccessful();

        $user = $user->fresh();
        $this->assertTrue($user->hasRole('Superuser'));
        $this->assertTrue($user->can('Manage Schools'));
        $this->assertSame(Permission::count(), $role->fresh()->permissions()->count());
    }

    public function test_it_reports_an_unknown_user(): void
    {
        $this->artisan('dcs:restore-superuser', ['username' => 'nobody'])->assertFailed();
    }
}
