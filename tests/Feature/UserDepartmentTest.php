<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Users belong to a school (head-office staff to none). The file keeps its old name.
 */
class UserDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $role;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        // The user permissions live in the hand-managed database, not in a migration.
        foreach (['View Users', 'Manage Users'] as $name) {
            $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $this->role = Role::create(['name' => 'Staff']);
        $this->school = School::create(['name' => 'Primary', 'is_active' => true]);
    }

    public function test_a_new_user_can_be_given_a_school(): void
    {
        $this->actingAs($this->admin)->post(route('users.register'), [
            'name' => 'Asha Juma',
            'username' => 'asha',
            'email' => 'asha@example.com',
            'role' => [$this->role->id],
            'school_id' => $this->school->id,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->school->id, User::where('username', 'asha')->first()->school_id);
    }

    public function test_a_user_can_hold_several_roles_and_the_edit_form_lists_them(): void
    {
        $second = Role::create(['name' => 'Storekeeper']);
        $user = User::factory()->create();

        $payload = ['UserID' => $user->id, 'name1' => $user->name, 'email1' => $user->email];

        $this->actingAs($this->admin)->post(route('users.update'), $payload + ['role1' => [$this->role->id, $second->id]])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Staff', 'Storekeeper'], $user->fresh()->getRoleNames()->all());

        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()
            ->assertSee('data-role-ids="['.$this->role->id.','.$second->id.']"', false);

        $this->actingAs($this->admin)->post(route('users.register'), [
            'name' => 'Neema', 'username' => 'neema', 'email' => 'n@example.com', 'role' => [$this->role->id, $second->id],
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasNoErrors();
        $this->assertCount(2, User::where('username', 'neema')->first()->roles);
    }

    public function test_head_office_staff_have_no_school(): void
    {
        $this->actingAs($this->admin)->post(route('users.register'), [
            'name' => 'Juma Ali', 'username' => 'juma', 'email' => 'juma@example.com', 'role' => [$this->role->id],
            'school_id' => '', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasNoErrors();

        $this->assertNull(User::where('username', 'juma')->first()->school_id);
    }

    public function test_an_existing_users_school_can_be_changed_and_cleared(): void
    {
        $user = User::factory()->create();

        $payload = ['UserID' => $user->id, 'name1' => $user->name, 'email1' => $user->email, 'role1' => [$this->role->id]];

        $this->actingAs($this->admin)->post(route('users.update'), $payload + ['school_id1' => $this->school->id])->assertSessionHasNoErrors();
        $this->assertSame($this->school->id, $user->fresh()->school_id);

        $this->actingAs($this->admin)->post(route('users.update'), $payload + ['school_id1' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->school_id);
    }

    public function test_the_users_list_shows_the_school(): void
    {
        $user = User::factory()->create(['name' => 'Asha Juma']);
        $user->forceFill(['school_id' => $this->school->id])->save();

        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->assertSee('Primary');
    }
}
