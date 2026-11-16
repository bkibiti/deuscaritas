<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('Manage Departments');
        $this->school = School::create(['name' => 'Primary', 'is_active' => true]);
    }

    public function test_store_creates_a_department_in_a_school(): void
    {
        $head = User::factory()->create();

        $response = $this->actingAs($this->user)->post(route('departments.store'), [
            'name' => 'Science',
            'school_id' => $this->school->id,
            'head_user_id' => $head->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', ['name' => 'Science', 'school_id' => $this->school->id, 'head_user_id' => $head->id]);
    }

    public function test_store_creates_a_department_that_serves_every_school(): void
    {
        $response = $this->actingAs($this->user)->post(route('departments.store'), ['name' => 'ICT', 'school_id' => '', 'is_active' => '1']);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', ['name' => 'ICT', 'school_id' => null]);
    }

    public function test_the_same_name_is_rejected_within_one_school(): void
    {
        Department::create(['name' => 'Science', 'school_id' => $this->school->id, 'is_active' => true]);

        $response = $this->actingAs($this->user)->post(route('departments.store'), ['name' => 'Science', 'school_id' => $this->school->id]);

        $response->assertSessionHasErrors('name');
    }

    public function test_the_same_name_is_rejected_among_all_school_departments(): void
    {
        Department::create(['name' => 'ICT', 'school_id' => null, 'is_active' => true]);

        $response = $this->actingAs($this->user)->post(route('departments.store'), ['name' => 'ICT', 'school_id' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_the_same_name_is_allowed_in_another_school(): void
    {
        $secondary = School::create(['name' => 'Secondary', 'is_active' => true]);
        Department::create(['name' => 'Science', 'school_id' => $this->school->id, 'is_active' => true]);

        $response = $this->actingAs($this->user)->post(route('departments.store'), ['name' => 'Science', 'school_id' => $secondary->id]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(2, Department::where('name', 'Science')->count());
    }

    public function test_update_can_keep_its_own_name(): void
    {
        $department = Department::create(['name' => 'Science', 'school_id' => $this->school->id, 'is_active' => true]);

        $response = $this->actingAs($this->user)->put(route('departments.update', $department), [
            'name' => 'Science',
            'school_id' => $this->school->id,
            'head_user_id' => $this->user->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($this->user->id, $department->fresh()->head_user_id);
    }

    public function test_a_department_can_be_deleted(): void
    {
        $department = Department::create(['name' => 'Science', 'school_id' => $this->school->id, 'is_active' => true]);

        $this->actingAs($this->user)->delete(route('departments.destroy', $department));

        $this->assertNull($department->fresh());
    }

    public function test_the_departments_index_renders(): void
    {
        $this->user->givePermissionTo('View Departments');
        Department::create(['name' => 'ICT', 'school_id' => null, 'is_active' => true]);

        $this->actingAs($this->user)->get(route('departments.index'))->assertOk()->assertSee('All schools');
    }

    public function test_a_user_without_permission_cannot_manage_departments(): void
    {
        $other = User::factory()->create();
        $department = Department::create(['name' => 'Science', 'school_id' => $this->school->id, 'is_active' => true]);

        $this->actingAs($other)->post(route('departments.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($other)->put(route('departments.update', $department), ['name' => 'X'])->assertForbidden();
        $this->actingAs($other)->delete(route('departments.destroy', $department))->assertForbidden();
    }
}
