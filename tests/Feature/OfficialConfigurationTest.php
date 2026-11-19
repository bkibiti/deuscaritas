<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The starting configuration from the "Departments & Stock Management" requirements
 * document, and the upgrade of a database that already held trial inventory data.
 */
class OfficialConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ten_official_departments_exist_for_all_schools(): void
    {
        $departments = Department::whereNull('school_id')->orderBy('name')->get();

        $this->assertSame([
            'Administration', 'Construction, Maintenance and Facilities', 'Environment', 'Finance', 'Food', 'Health',
            'ICT and Technology', 'Sports and Games', 'Teaching and Learning', 'Transport',
        ], $departments->pluck('name')->all());

        $this->assertFalse(Schema::hasColumn('departments', 'description'));
        $this->assertTrue($departments->every(fn (Department $department) => $department->is_active));
    }

    public function test_the_ten_stock_categories_and_their_items_exist(): void
    {
        $this->assertSame([
            'Cleaning Materials', 'Food Stock', 'Furniture and General Assets', 'ICT and Electronics',
            'Kitchen and Cooking Equipment', 'Maintenance and Hardware', 'Medical and First Aid', 'Sports and Games',
            'Stationery Stock', 'Uniforms and School Requirements',
        ], ItemCategory::orderBy('name')->pluck('name')->all());

        $this->assertSame(72, Item::count());
        $this->assertSame(0, Item::whereNull('item_category_id')->count());
        $this->assertSame(0, ItemCategory::doesntHave('items')->count());

        $unitOf = fn (string $name) => Item::where('name', $name)->first()->unit->abbreviation;
        $this->assertSame('bag', $unitOf('Maize'));
        $this->assertSame('L', $unitOf('Cooking oil'));
        $this->assertSame('dz', $unitOf('Exercise books'));
        $this->assertSame('pair', $unitOf('Medical gloves'));
        $this->assertSame('m', $unitOf('Electrical cables'));
    }

    public function test_the_units_the_document_lists_exist(): void
    {
        $abbreviations = Unit::pluck('abbreviation')->all();

        foreach (['bag', 'kg', 'g', 'L', 'ml', 'pc', 'dz', 'box', 'ctn', 'pkt', 'roll', 'set', 'pair', 'btl', 'm'] as $abbreviation) {
            $this->assertContains($abbreviation, $abbreviations);
        }
    }

    public function test_the_departments_screen_lists_the_official_departments(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['View Departments', 'Manage Departments']);
        $transport = Department::where('name', 'Transport')->first();

        $this->actingAs($admin)->get(route('departments.index'))
            ->assertOk()->assertSee('ICT and Technology')->assertDontSee('Responsibilities');

        $this->actingAs($admin)->put(route('departments.update', $transport), [
            'name' => 'Transport', 'school_id' => '', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($transport->fresh()->school_id);
    }

    /**
     * Rebuild the state of a database from before the shared catalogue (items and categories
     * owned by stores, with the trial data and a name used in both stores), then upgrade it.
     */
    public function test_a_database_with_trial_store_owned_items_upgrades_cleanly(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 8]);

        DB::table('inv_items')->delete();
        DB::table('inv_item_categories')->delete();

        $food = DB::table('inv_stores')->where('code', 'FOOD')->value('id');
        $building = DB::table('inv_stores')->where('code', 'BUILD')->value('id');
        $kg = DB::table('inv_units')->where('abbreviation', 'kg')->value('id');

        $cereals = DB::table('inv_item_categories')->insertGetId(['store_id' => $food, 'name' => 'Cereals and grains']);
        $generalFood = DB::table('inv_item_categories')->insertGetId(['store_id' => $food, 'name' => 'General']);
        $generalBuilding = DB::table('inv_item_categories')->insertGetId(['store_id' => $building, 'name' => 'General']);
        $hardware = DB::table('inv_item_categories')->insertGetId(['store_id' => $building, 'name' => 'Hardware']);

        $item = fn (int $store, ?int $category, string $name) => DB::table('inv_items')->insertGetId([
            'store_id' => $store, 'item_category_id' => $category, 'unit_id' => $kg, 'name' => $name, 'reorder_level' => 5, 'is_active' => true,
        ]);
        $rice = $item($food, $cereals, 'Rice');
        $item($building, $hardware, 'Nails 4 inch');
        $item($food, $generalFood, 'Rope');
        $buildingRope = $item($building, $generalBuilding, 'Rope');

        Artisan::call('migrate');

        // Trial items move to the official categories, keep their own unit and settings, and are not doubled.
        $this->assertSame(1, Item::where('name', 'Rice')->count());
        $this->assertSame('Food Stock', Item::find($rice)->category->name);
        $this->assertSame('kg', Item::find($rice)->unit->abbreviation);
        $this->assertSame('5.000', Item::find($rice)->reorder_level);
        $this->assertSame('Maintenance and Hardware', Item::where('name', 'Nails 4 inch')->first()->category->name);
        $this->assertNull(ItemCategory::where('name', 'Cereals and grains')->first());
        $this->assertNull(ItemCategory::where('name', 'Hardware')->first());

        // A name used in both stores: the categories merge, the second item is renamed.
        $this->assertSame(1, ItemCategory::where('name', 'General')->count());
        $this->assertSame('Rope (Building Materials Store)', Item::find($buildingRope)->name);
        $this->assertSame(2, ItemCategory::where('name', 'General')->first()->items()->count());

        $this->assertSame(72 + 3, Item::count());
        $this->assertSame(10, Department::whereNull('school_id')->count(), 'Running the configuration again adds no second copy.');
    }
}
