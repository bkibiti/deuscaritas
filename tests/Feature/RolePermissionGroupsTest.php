<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PermissionGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_grouped_by_area_with_view_before_manage(): void
    {
        $names = ['Manage Schools', 'View Stock', 'Approve Loans', 'View Users', 'View Schools', 'Receive Stock', 'Manage Users', 'View Administration', 'Assign Requests', 'View All Requests'];
        $permissions = collect($names)->map(fn ($name, $i) => (object) ['id' => $i + 1, 'name' => $name]);

        $groups = PermissionGroups::arrange($permissions);

        $this->assertSame(['Requests', 'Inventory and stock', 'Administration', 'User Management', 'Other'], array_keys($groups));
        $this->assertSame(['Assign Requests', 'View All Requests'], $groups['Requests']->pluck('name')->sort()->values()->all());
        $this->assertSame(['View Stock', 'Receive Stock'], $groups['Inventory and stock']->pluck('name')->all());
        $this->assertSame(['View Administration', 'View Schools', 'Manage Schools'], $groups['Administration']->pluck('name')->all());
        $this->assertSame(['View Users', 'Manage Users'], $groups['User Management']->pluck('name')->all());
        $this->assertSame(['Approve Loans'], $groups['Other']->pluck('name')->all());
        $this->assertSame(count($names), collect($groups)->sum(fn ($group) => $group->count()), 'Every permission appears exactly once.');
    }

    public function test_the_role_forms_show_every_permission_under_a_group(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'Manage Roles', 'guard_name' => 'web']));
        $role = Role::create(['name' => 'Storekeeper', 'description' => 'Keeps the store']);
        $role->givePermissionTo('View Stock');

        $create = $this->actingAs($admin)->get(route('roles.create'))->assertOk();
        $create->assertSee('Inventory and stock')->assertSee('Administration')->assertSee('Check all');
        foreach (Permission::pluck('name') as $name) {
            $create->assertSee($name);
        }

        $edit = $this->actingAs($admin)->get(route('roles.edit', $role->id))->assertOk()->assertSee('Inventory and stock');
        $viewStock = Permission::where('name', 'View Stock')->value('id');
        $this->assertMatchesRegularExpression('/value="'.$viewStock.'"\s+checked/', $edit->getContent(), 'The role keeps its ticks.');
    }

    public function test_the_masters_permission_is_renamed_and_keeps_its_roles(): void
    {
        $old = Permission::firstOrCreate(['name' => 'View Masters', 'guard_name' => 'web']);
        Permission::where('name', 'View Administration')->delete();
        $role = Role::create(['name' => 'Staff']);
        $role->givePermissionTo($old);

        (require database_path('migrations/2026_11_25_000001_rename_masters_permission.php'))->up();

        $this->assertNull(Permission::where('name', 'View Masters')->first());
        $this->assertSame(['View Administration'], $role->fresh()->permissions->pluck('name')->all());
    }
}
