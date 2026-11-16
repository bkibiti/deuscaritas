<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('Manage Schools');
    }

    public function test_store_creates_a_school_with_its_head(): void
    {
        $head = User::factory()->create();

        $response = $this->actingAs($this->user)->post(route('schools.store'), [
            'name' => 'Deus Caritas Primary',
            'code' => 'DCP',
            'head_user_id' => $head->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('schools', ['name' => 'Deus Caritas Primary', 'code' => 'DCP', 'head_user_id' => $head->id, 'is_active' => true]);
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        School::create(['name' => 'Primary', 'is_active' => true]);

        $response = $this->actingAs($this->user)->post(route('schools.store'), ['name' => 'Primary']);

        $response->assertSessionHasErrors('name');
    }

    public function test_update_can_keep_its_own_name_and_deactivate(): void
    {
        $school = School::create(['name' => 'Primary', 'is_active' => true]);

        $response = $this->actingAs($this->user)->put(route('schools.update', $school), ['name' => 'Primary']);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($school->fresh()->is_active);
    }

    public function test_a_school_with_departments_cannot_be_deleted(): void
    {
        $school = School::create(['name' => 'Primary', 'is_active' => true]);
        Department::create(['name' => 'Science', 'school_id' => $school->id, 'is_active' => true]);

        $this->actingAs($this->user)->delete(route('schools.destroy', $school));

        $this->assertNotNull($school->fresh());
    }

    public function test_a_school_without_departments_can_be_deleted(): void
    {
        $school = School::create(['name' => 'Primary', 'is_active' => true]);

        $this->actingAs($this->user)->delete(route('schools.destroy', $school));

        $this->assertNull($school->fresh());
    }

    public function test_the_schools_index_renders(): void
    {
        $this->user->givePermissionTo('View Schools');
        School::create(['name' => 'Primary', 'head_user_id' => $this->user->id, 'is_active' => true]);

        $this->actingAs($this->user)->get(route('schools.index'))->assertOk()->assertSee('Primary');
    }

    public function test_a_user_without_permission_cannot_manage_schools(): void
    {
        $other = User::factory()->create();
        $school = School::create(['name' => 'Primary', 'is_active' => true]);

        $this->actingAs($other)->post(route('schools.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($other)->put(route('schools.update', $school), ['name' => 'X'])->assertForbidden();
        $this->actingAs($other)->delete(route('schools.destroy', $school))->assertForbidden();
    }
}
