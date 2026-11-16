<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $role;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        // The user permissions live in the hand-managed database, not in a migration.
        foreach (['View Users', 'Manage Users'] as $name) {
            $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $this->role = Role::create(['name' => 'Staff']);
        $this->department = Department::create(['name' => 'ICT', 'is_active' => true]);
    }

    public function test_a_new_user_can_be_given_a_department(): void
    {
        $this->actingAs($this->admin)->post(route('users.register'), [
            'name' => 'Asha Juma',
            'username' => 'asha',
            'email' => 'asha@example.com',
            'role' => [$this->role->id],
            'department_id' => $this->department->id,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->department->id, User::where('username', 'asha')->first()->department_id);
    }

    public function test_an_existing_users_department_can_be_changed_and_cleared(): void
    {
        $user = User::factory()->create();

        $payload = ['UserID' => $user->id, 'name1' => $user->name, 'email1' => $user->email, 'role1' => [$this->role->id]];

        $this->actingAs($this->admin)->post(route('users.update'), $payload + ['department_id1' => $this->department->id])->assertSessionHasNoErrors();
        $this->assertSame($this->department->id, $user->fresh()->department_id);

        $this->actingAs($this->admin)->post(route('users.update'), $payload + ['department_id1' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->department_id);
    }

    public function test_the_users_list_shows_the_department(): void
    {
        $user = User::factory()->create(['name' => 'Asha Juma']);
        $user->department_id = $this->department->id;
        $user->save();

        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->assertSee('ICT (All schools)');
    }
}
