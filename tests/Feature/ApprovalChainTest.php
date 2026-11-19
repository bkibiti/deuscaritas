<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chain_is_seeded_in_signing_order_with_roles(): void
    {
        $this->assertSame(
            ['school_head', 'coordinator', 'manager', 'director'],
            WorkflowStep::orderBy('position')->pluck('key')->all(),
            'There is no department head step.'
        );
        $this->assertSame('director_rule', WorkflowStep::where('key', 'director')->value('condition'));
        $this->assertSame('Head of School', WorkflowStep::where('key', 'school_head')->first()->role->name);
        $this->assertTrue(WorkflowStep::where('key', 'school_head')->first()->isSchoolScoped());
        $this->assertFalse(WorkflowStep::where('key', 'manager')->first()->isSchoolScoped());
    }

    public function test_an_admin_chooses_the_role_that_signs_a_step(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['View Approval Chain', 'Manage Approval Chain']);
        $role = Role::create(['name' => 'Bursar']);
        $step = WorkflowStep::where('key', 'manager')->first();

        $this->actingAs($admin)->get(route('workflow-steps.index'))->assertOk()->assertSee('Head of School')->assertDontSee('Head of Department');
        $this->actingAs($admin)
            ->put(route('workflow-steps.update', $step), ['role_id' => $role->id, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame($role->id, $step->fresh()->role_id);
        $this->assertTrue($step->fresh()->is_active);
    }

    public function test_a_step_can_be_switched_off_and_a_role_must_exist(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('Manage Approval Chain');
        $step = WorkflowStep::where('key', 'school_head')->first();

        $this->actingAs($admin)->put(route('workflow-steps.update', $step), ['role_id' => 99999])->assertSessionHasErrors('role_id');

        $this->actingAs($admin)->put(route('workflow-steps.update', $step), ['role_id' => $step->role_id])->assertSessionHasNoErrors();
        $this->assertFalse($step->fresh()->is_active);
    }

    public function test_a_user_without_permission_cannot_change_the_chain(): void
    {
        $user = User::factory()->create();
        $step = WorkflowStep::where('key', 'manager')->first();

        $this->actingAs($user)->get(route('workflow-steps.index'))->assertForbidden();
        $this->actingAs($user)->put(route('workflow-steps.update', $step), ['role_id' => $step->role_id])->assertForbidden();
    }
}
