<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_logged_in_user_sees_the_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertOk();
        $response->assertViewHas('tiles');
    }

    public function test_the_dashboard_has_no_set_up_section(): void
    {
        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertOk()->assertDontSee('Set-up');
    }

    public function test_master_data_links_are_hidden_without_permission(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertDontSee(route('schools.index'));
    }

    public function test_the_sidebar_shows_masters_to_a_user_who_can_view_schools(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('View Schools');

        $this->actingAs($user)->get(route('home'))->assertSee(route('schools.index'));
    }
}
