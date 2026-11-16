<?php

namespace Tests\Feature;

use App\Models\RequestCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('Manage Request Categories');
    }

    public function test_the_starting_categories_are_seeded(): void
    {
        $this->assertDatabaseHas('request_categories', ['name' => 'ICT Support', 'requires_director' => false, 'is_active' => true]);
    }

    public function test_store_creates_a_category_with_a_director_threshold(): void
    {
        $response = $this->actingAs($this->user)->post(route('request-categories.store'), [
            'name' => 'Construction',
            'director_cost_threshold' => 500000,
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('request_categories', ['name' => 'Construction', 'director_cost_threshold' => 500000, 'requires_director' => false]);
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        $response = $this->actingAs($this->user)->post(route('request-categories.store'), ['name' => 'Transport']);

        $response->assertSessionHasErrors('name');
    }

    public function test_update_can_clear_the_threshold(): void
    {
        $category = RequestCategory::create(['name' => 'Construction', 'director_cost_threshold' => 500000, 'is_active' => true]);

        $response = $this->actingAs($this->user)->put(route('request-categories.update', $category), [
            'name' => 'Construction',
            'director_cost_threshold' => '',
            'requires_director' => '1',
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($category->fresh()->director_cost_threshold);
        $this->assertTrue($category->fresh()->requires_director);
    }

    public function test_a_category_can_be_deleted(): void
    {
        $category = RequestCategory::create(['name' => 'Construction', 'is_active' => true]);

        $this->actingAs($this->user)->delete(route('request-categories.destroy', $category));

        $this->assertNull($category->fresh());
    }

    public function test_needs_director_follows_the_flag_and_the_threshold(): void
    {
        $always = new RequestCategory(['requires_director' => true]);
        $byCost = new RequestCategory(['requires_director' => false, 'director_cost_threshold' => 500000]);
        $never = new RequestCategory(['requires_director' => false]);

        $this->assertTrue($always->needsDirector(null));
        $this->assertTrue($byCost->needsDirector(500000));
        $this->assertFalse($byCost->needsDirector(499999));
        $this->assertFalse($byCost->needsDirector(null));
        $this->assertFalse($never->needsDirector(10000000));
    }

    public function test_the_request_categories_index_renders(): void
    {
        $this->user->givePermissionTo('View Request Categories');

        $this->actingAs($this->user)->get(route('request-categories.index'))->assertOk()->assertSee('Maintenance');
    }

    public function test_a_user_without_permission_cannot_manage_request_categories(): void
    {
        $other = User::factory()->create();
        $category = RequestCategory::create(['name' => 'Construction', 'is_active' => true]);

        $this->actingAs($other)->post(route('request-categories.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($other)->put(route('request-categories.update', $category), ['name' => 'X'])->assertForbidden();
        $this->actingAs($other)->delete(route('request-categories.destroy', $category))->assertForbidden();
    }
}
