<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\School;
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
        $response->assertViewHas('counts');
    }

    public function test_the_dashboard_counts_only_active_master_data(): void
    {
        $school = School::create(['name' => 'Primary', 'is_active' => true]);
        School::create(['name' => 'Closed Campus', 'is_active' => false]);
        Department::create(['name' => 'ICT', 'school_id' => $school->id, 'is_active' => true]);

        $counts = collect($this->actingAs(User::factory()->create())->get(route('home'))->viewData('counts'))
            ->pluck('value', 'label');

        $this->assertSame(1, $counts['Schools']);
        // The ten official departments, plus this one.
        $this->assertSame(11, $counts['Departments']);
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
