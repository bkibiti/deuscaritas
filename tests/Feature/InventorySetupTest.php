<?php

namespace Tests\Feature;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Store;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Inventory set-up: stores and their keepers, units, and the shared catalogue of categories,
 * items and suppliers.
 */
class InventorySetupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Unit $kg;

    private ItemCategory $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'View Inventory Setup', 'Manage Inventory Setup', 'View Inventory Items', 'Manage Inventory Items',
            'View Suppliers', 'Manage Suppliers',
        ]);

        $this->kg = Unit::where('abbreviation', 'kg')->first();
        $this->food = ItemCategory::where('name', 'Food Stock')->first();
    }

    public function test_every_inventory_screen_renders(): void
    {
        Supplier::create(['name' => 'Azam Traders', 'is_active' => true]);
        Store::first()->keepers()->sync([$this->admin->id]);

        $this->actingAs($this->admin)->get(route('inventory.stores.index'))->assertOk()->assertSee('Food Store')->assertSee($this->admin->name);
        $this->actingAs($this->admin)->get(route('inventory.units.index'))->assertOk()->assertSee('Kilogram');
        $this->actingAs($this->admin)->get(route('inventory.categories.index'))->assertOk()->assertSee('Stationery Stock');
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

    public function test_a_store_name_must_be_unique(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.stores.store'), ['name' => 'Food Store'])->assertSessionHasErrors('name');
    }

    public function test_units_are_unique_and_protected_while_in_use(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.units.store'), ['name' => 'Kilogram', 'abbreviation' => 'kgs'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('inventory.units.store'), ['name' => 'Crate', 'abbreviation' => 'crt'])->assertSessionHasNoErrors();

        $crate = Unit::where('abbreviation', 'crt')->first();

        $this->actingAs($this->admin)->delete(route('inventory.units.destroy', $this->kg));
        $this->actingAs($this->admin)->delete(route('inventory.units.destroy', $crate));

        $this->assertNotNull($this->kg->fresh(), 'Kilogram is used by items.');
        $this->assertNull($crate->fresh());
    }

    public function test_a_category_name_is_unique_and_a_category_with_items_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.categories.store'), ['name' => 'Food Stock'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('inventory.categories.store'), ['name' => 'Laboratory Supplies'])->assertSessionHasNoErrors();

        $laboratory = ItemCategory::where('name', 'Laboratory Supplies')->first();

        $this->actingAs($this->admin)->put(route('inventory.categories.update', $laboratory), ['name' => 'Laboratory Stock'])->assertSessionHasNoErrors();
        $this->assertSame('Laboratory Stock', $laboratory->fresh()->name);

        $this->actingAs($this->admin)->delete(route('inventory.categories.destroy', $this->food));
        $this->actingAs($this->admin)->delete(route('inventory.categories.destroy', $laboratory));

        $this->assertNotNull($this->food->fresh());
        $this->assertNull($laboratory->fresh());
    }

    public function test_an_item_is_created_with_a_category_unit_and_reorder_level(): void
    {
        $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_category_id' => $this->food->id,
            'unit_id' => $this->kg->id,
            'name' => 'Groundnuts',
            'code' => 'FD-GN',
            'reorder_level' => 20,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $item = Item::where('code', 'FD-GN')->first();
        $this->assertSame($this->food->id, $item->item_category_id);
        $this->assertSame('20.000', $item->reorder_level);
        $this->assertTrue($item->is_active);
    }

    public function test_item_names_and_codes_are_unique(): void
    {
        Item::create(['unit_id' => $this->kg->id, 'name' => 'Groundnuts', 'code' => 'G1', 'is_active' => true]);

        $this->actingAs($this->admin)->post(route('inventory.items.store'), ['unit_id' => $this->kg->id, 'name' => 'Groundnuts'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('inventory.items.store'), ['unit_id' => $this->kg->id, 'name' => 'Cashews', 'code' => 'G1'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('inventory.items.store'), ['unit_id' => $this->kg->id, 'name' => 'Cashews'])->assertSessionHasNoErrors();
    }

    public function test_the_items_list_can_be_filtered_by_category(): void
    {
        $this->actingAs($this->admin)
            ->get(route('inventory.items.index', ['category' => $this->food->id]))
            ->assertOk()->assertSee('Cooking oil')->assertDontSee('Whiteboards');
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
