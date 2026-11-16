<?php

namespace Tests\Feature;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Store;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Unit;
use App\Models\User;
use Database\Seeders\InventorySampleItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Inventory set-up: stores and their keepers, units, categories, items and suppliers.
 */
class InventorySetupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $food;

    private Store $building;

    private Unit $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'View Inventory Setup', 'Manage Inventory Setup', 'View Inventory Items', 'Manage Inventory Items',
            'View Suppliers', 'Manage Suppliers',
        ]);

        $this->food = Store::where('code', 'FOOD')->first();
        $this->building = Store::where('code', 'BUILD')->first();
        $this->kg = Unit::where('abbreviation', 'kg')->first();
    }

    public function test_the_two_stores_and_common_units_are_seeded(): void
    {
        $this->assertSame(['Building Materials Store', 'Food Store'], Store::orderBy('name')->pluck('name')->all());
        $this->assertNotNull($this->kg);
        $this->assertNotNull(Unit::where('abbreviation', 'bag')->first());
    }

    public function test_every_inventory_screen_renders(): void
    {
        $category = ItemCategory::create(['store_id' => $this->food->id, 'name' => 'Cereals']);
        Item::create(['store_id' => $this->food->id, 'item_category_id' => $category->id, 'unit_id' => $this->kg->id, 'name' => 'Maize flour', 'reorder_level' => 50, 'is_active' => true]);
        Supplier::create(['name' => 'Azam Traders', 'is_active' => true]);
        $this->food->keepers()->sync([$this->admin->id]);

        $this->actingAs($this->admin)->get(route('inventory.stores.index'))->assertOk()->assertSee('Food Store')->assertSee($this->admin->name);
        $this->actingAs($this->admin)->get(route('inventory.units.index'))->assertOk()->assertSee('Kilogram');
        $this->actingAs($this->admin)->get(route('inventory.categories.index'))->assertOk()->assertSee('Cereals');
        $this->actingAs($this->admin)->get(route('inventory.items.index'))->assertOk()->assertSee('Maize flour');
        $this->actingAs($this->admin)->get(route('inventory.suppliers.index'))->assertOk()->assertSee('Azam Traders');
        $this->actingAs($this->admin)->get(route('home'))->assertSee(route('inventory.items.index'));
    }

    public function test_a_store_is_created_with_its_keepers_and_they_can_be_changed(): void
    {
        [$first, $second] = User::factory()->count(2)->create()->all();

        $this->actingAs($this->admin)->post(route('inventory.stores.store'), [
            'name' => 'Stationery Store', 'code' => 'STAT', 'keepers' => [$first->id, $second->id], 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $store = Store::where('code', 'STAT')->first();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $store->keepers->pluck('id')->all());

        $this->actingAs($this->admin)->put(route('inventory.stores.update', $store), [
            'name' => 'Stationery Store', 'code' => 'STAT', 'keepers' => [$second->id],
        ])->assertSessionHasNoErrors();

        $store = $store->fresh();
        $this->assertSame([$second->id], $store->keepers->pluck('id')->all());
        $this->assertFalse($store->is_active);
    }

    public function test_a_store_name_must_be_unique_and_a_store_with_items_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.stores.store'), ['name' => 'Food Store'])->assertSessionHasErrors('name');

        Item::create(['store_id' => $this->food->id, 'unit_id' => $this->kg->id, 'name' => 'Rice', 'is_active' => true]);

        $this->actingAs($this->admin)->delete(route('inventory.stores.destroy', $this->food));
        $this->actingAs($this->admin)->delete(route('inventory.stores.destroy', $this->building));

        $this->assertNotNull($this->food->fresh());
        $this->assertNull($this->building->fresh());
    }

    public function test_units_are_unique_and_protected_while_in_use(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.units.store'), ['name' => 'Kilogram', 'abbreviation' => 'kgs'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('inventory.units.store'), ['name' => 'Crate', 'abbreviation' => 'crt'])->assertSessionHasNoErrors();

        Item::create(['store_id' => $this->food->id, 'unit_id' => $this->kg->id, 'name' => 'Rice', 'is_active' => true]);
        $crate = Unit::where('abbreviation', 'crt')->first();

        $this->actingAs($this->admin)->delete(route('inventory.units.destroy', $this->kg));
        $this->actingAs($this->admin)->delete(route('inventory.units.destroy', $crate));

        $this->assertNotNull($this->kg->fresh());
        $this->assertNull($crate->fresh());
    }

    public function test_a_category_name_is_unique_within_its_store_only(): void
    {
        ItemCategory::create(['store_id' => $this->food->id, 'name' => 'General']);

        $this->actingAs($this->admin)->post(route('inventory.categories.store'), ['store_id' => $this->food->id, 'name' => 'General'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('inventory.categories.store'), ['store_id' => $this->building->id, 'name' => 'General'])->assertSessionHasNoErrors();

        $this->assertSame(2, ItemCategory::where('name', 'General')->count());
    }

    public function test_a_category_with_items_cannot_move_store_or_be_deleted(): void
    {
        $category = ItemCategory::create(['store_id' => $this->food->id, 'name' => 'Cereals']);
        Item::create(['store_id' => $this->food->id, 'item_category_id' => $category->id, 'unit_id' => $this->kg->id, 'name' => 'Rice', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('inventory.categories.update', $category), ['store_id' => $this->building->id, 'name' => 'Cereals'])
            ->assertSessionHasErrors('store_id');
        $this->actingAs($this->admin)->delete(route('inventory.categories.destroy', $category));

        $this->assertSame($this->food->id, $category->fresh()->store_id);
    }

    public function test_an_item_is_created_in_a_store_with_a_unit_and_reorder_level(): void
    {
        $category = ItemCategory::create(['store_id' => $this->building->id, 'name' => 'Cement']);

        $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'store_id' => $this->building->id,
            'item_category_id' => $category->id,
            'unit_id' => Unit::where('abbreviation', 'bag')->value('id'),
            'name' => 'Cement 42.5N',
            'code' => 'CEM-425',
            'reorder_level' => 20,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $item = Item::where('code', 'CEM-425')->first();
        $this->assertSame($this->building->id, $item->store_id);
        $this->assertSame('20.000', $item->reorder_level);
        $this->assertTrue($item->is_active);
    }

    public function test_an_item_cannot_take_a_category_from_another_store(): void
    {
        $foodCategory = ItemCategory::create(['store_id' => $this->food->id, 'name' => 'Cereals']);

        $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'store_id' => $this->building->id,
            'item_category_id' => $foodCategory->id,
            'unit_id' => $this->kg->id,
            'name' => 'Nails',
        ])->assertSessionHasErrors('item_category_id');

        $this->assertSame(0, Item::count());
    }

    public function test_an_item_name_is_unique_within_its_store_and_codes_are_unique_everywhere(): void
    {
        Item::create(['store_id' => $this->food->id, 'unit_id' => $this->kg->id, 'name' => 'Salt', 'code' => 'S1', 'is_active' => true]);
        $base = ['unit_id' => $this->kg->id, 'name' => 'Salt'];

        $this->actingAs($this->admin)->post(route('inventory.items.store'), $base + ['store_id' => $this->food->id])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('inventory.items.store'), $base + ['store_id' => $this->building->id, 'code' => 'S1'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('inventory.items.store'), $base + ['store_id' => $this->building->id])->assertSessionHasNoErrors();
    }

    public function test_the_items_list_can_be_filtered_by_store(): void
    {
        Item::create(['store_id' => $this->food->id, 'unit_id' => $this->kg->id, 'name' => 'Beans', 'is_active' => true]);
        Item::create(['store_id' => $this->building->id, 'unit_id' => $this->kg->id, 'name' => 'Binding wire', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('inventory.items.index', ['store' => $this->food->id]))
            ->assertOk()->assertSee('Beans')->assertDontSee('Binding wire');
    }

    public function test_a_supplier_is_created_updated_and_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.suppliers.store'), [
            'name' => 'Azam Traders', 'phone' => '0712345678', 'email' => 'sales@azam.example', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('inventory.suppliers.store'), ['name' => 'Azam Traders'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('inventory.suppliers.store'), ['name' => 'Other', 'email' => 'not-an-email'])->assertSessionHasErrors('email');

        $supplier = Supplier::first();
        $this->actingAs($this->admin)->put(route('inventory.suppliers.update', $supplier), ['name' => 'Azam Traders Ltd'])->assertSessionHasNoErrors();
        $this->assertSame('Azam Traders Ltd', $supplier->fresh()->name);
        $this->assertFalse($supplier->fresh()->is_active);

        $this->actingAs($this->admin)->delete(route('inventory.suppliers.destroy', $supplier));
        $this->assertNull($supplier->fresh());
    }

    public function test_the_sample_seeder_adds_five_items_to_each_store_once(): void
    {
        $this->seed(InventorySampleItemsSeeder::class);
        $this->seed(InventorySampleItemsSeeder::class);

        $this->assertSame(5, $this->food->items()->count());
        $this->assertSame(5, $this->building->items()->count());
        $this->assertSame('bag', Item::where('name', 'Cement 42.5N')->first()->unit->abbreviation);
        $this->assertSame($this->food->id, Item::where('name', 'Rice')->first()->category->store_id);
    }

    public static function screensProvider(): array
    {
        return [
            'stores' => ['inventory.stores'],
            'units' => ['inventory.units'],
            'categories' => ['inventory.categories'],
            'items' => ['inventory.items'],
            'suppliers' => ['inventory.suppliers'],
        ];
    }

    #[DataProvider('screensProvider')]
    public function test_a_user_without_permission_is_kept_out(string $route): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route($route.'.index'))->assertForbidden();
        $this->actingAs($user)->post(route($route.'.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->get(route('home'))->assertDontSee(route('inventory.items.index'));
    }
}
