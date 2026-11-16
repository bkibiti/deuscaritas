<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chain_is_seeded_in_signing_order(): void
    {
        $this->assertSame(
            ['department_head', 'school_head', 'coordinator', 'manager', 'director'],
            WorkflowStep::orderBy('position')->pluck('key')->all()
        );
        $this->assertSame('director_rule', WorkflowStep::where('key', 'director')->value('condition'));
    }

    public function test_an_admin_sets_who_signs_a_named_step(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['View Approval Chain', 'Manage Approval Chain']);
        $manager = User::factory()->create();
        $step = WorkflowStep::where('key', 'manager')->first();

        $this->actingAs($admin)->get(route('workflow-steps.index'))->assertOk()->assertSee('Head of Department');
        $this->actingAs($admin)
            ->put(route('workflow-steps.update', $step), ['user_id' => $manager->id, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame($manager->id, $step->fresh()->user_id);
        $this->assertTrue($step->fresh()->is_active);
    }

    public function test_a_head_step_never_takes_a_named_user_but_can_be_switched_off(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('Manage Approval Chain');
        $step = WorkflowStep::where('key', 'school_head')->first();

        $this->actingAs($admin)->put(route('workflow-steps.update', $step), ['user_id' => $admin->id]);

        $this->assertNull($step->fresh()->user_id);
        $this->assertFalse($step->fresh()->is_active);
    }

    public function test_a_user_without_permission_cannot_change_the_chain(): void
    {
        $user = User::factory()->create();
        $step = WorkflowStep::where('key', 'manager')->first();

        $this->actingAs($user)->get(route('workflow-steps.index'))->assertForbidden();
        $this->actingAs($user)->put(route('workflow-steps.update', $step), ['user_id' => $user->id])->assertForbidden();
    }
}
