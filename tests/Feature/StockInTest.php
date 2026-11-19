<?php

namespace Tests\Feature;

use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockDocument;
use App\Models\Inventory\Store;
use App\Models\Inventory\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock In: drafts change nothing, confirming raises the shared balance and writes the ledger.
 */
class StockInTest extends TestCase
{
    use RefreshDatabase;

    private User $keeper;

    private Store $food;

    private Store $building;

    private Supplier $supplier;

    private Item $rice;

    private Item $beans;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keeper = User::factory()->create();
        $this->keeper->givePermissionTo(['View Stock', 'Receive Stock']);

        $this->food = Store::where('code', 'FOOD')->first();
        $this->building = Store::where('code', 'BUILD')->first();
        $this->food->keepers()->sync([$this->keeper->id]);

        $this->supplier = Supplier::create(['name' => 'Azam Traders', 'is_active' => true]);
        [$this->rice, $this->beans] = Item::where('is_active', true)->orderBy('id')->take(2)->get()->all();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'type' => 'receipt',
            'store_id' => $this->food->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'DN-100',
            'document_date' => now('Africa/Dar_es_Salaam')->toDateString(),
            'items' => [
                ['item_id' => $this->rice->id, 'quantity' => 100, 'unit_cost' => 2000],
                ['item_id' => $this->beans->id, 'quantity' => 50, 'unit_cost' => 3000],
            ],
        ], $override);
    }

    public function test_a_draft_changes_no_stock(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['action' => 'draft']))->assertRedirect();

        $document = StockDocument::first();
        $this->assertSame('draft', $document->status);
        $this->assertStringStartsWith('SIN-', $document->number);
        $this->assertSame(0, StockBalance::count());
    }

    public function test_confirming_raises_the_balance_and_writes_the_ledger(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['action' => 'confirm']))->assertRedirect();

        $balance = StockBalance::where(['store_id' => $this->food->id, 'item_id' => $this->rice->id])->first();
        $this->assertEquals(100, (float) $balance->quantity);
        $this->assertEquals(200000, (float) $balance->value);
        $this->assertSame('confirmed', StockDocument::first()->status);
        $this->assertDatabaseCount('inv_stock_ledger', 2);

        // A second delivery at a new price gives a weighted average cost.
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload([
            'action' => 'confirm',
            'items' => [['item_id' => $this->rice->id, 'quantity' => 100, 'unit_cost' => 2200]],
        ]));

        $balance = StockBalance::where(['store_id' => $this->food->id, 'item_id' => $this->rice->id])->first();
        $this->assertEquals(200, (float) $balance->quantity);
        $this->assertEquals(2100, $balance->averageCost());
    }

    public function test_a_confirmed_document_cannot_be_confirmed_twice_or_deleted(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['action' => 'confirm']));
        $document = StockDocument::first();

        $this->actingAs($this->keeper)->post(route('inventory.stock-in.confirm', $document))->assertSessionHas('alert-danger');
        $this->actingAs($this->keeper)->delete(route('inventory.stock-in.destroy', $document))->assertSessionHas('alert-danger');

        $this->assertEquals(100, (float) StockBalance::where('item_id', $this->rice->id)->value('quantity'));
        $this->assertDatabaseHas('inv_stock_documents', ['id' => $document->id]);
    }

    public function test_a_draft_can_be_confirmed_later_or_deleted(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['action' => 'draft']));
        $document = StockDocument::first();

        $this->actingAs($this->keeper)->post(route('inventory.stock-in.confirm', $document))->assertRedirect();
        $this->assertEquals(100, (float) StockBalance::where('item_id', $this->rice->id)->value('quantity'));

        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['action' => 'draft']));
        $draft = StockDocument::latest('id')->first();
        $this->actingAs($this->keeper)->delete(route('inventory.stock-in.destroy', $draft));
        $this->assertDatabaseMissing('inv_stock_documents', ['id' => $draft->id]);
    }

    public function test_a_keeper_can_only_receive_into_their_own_store(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['store_id' => $this->building->id, 'action' => 'confirm']))
            ->assertSessionHasErrors('store_id');

        $this->assertSame(0, StockDocument::count());
    }

    public function test_opening_stock_needs_no_supplier_but_a_delivery_does(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['supplier_id' => null, 'action' => 'draft']))
            ->assertSessionHasErrors('supplier_id');

        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['type' => 'opening', 'supplier_id' => null, 'action' => 'confirm']))
            ->assertSessionHasNoErrors();

        $this->assertSame('opening', StockDocument::first()->type);
        $this->assertNull(StockDocument::first()->supplier_id);
    }

    public function test_lines_are_validated(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload([
            'items' => [['item_id' => $this->rice->id, 'quantity' => 0, 'unit_cost' => 10], ['item_id' => $this->rice->id, 'quantity' => 1, 'unit_cost' => 10]],
        ]))->assertSessionHasErrors(['items.0.quantity', 'items.1.item_id']);

        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['items' => []]))->assertSessionHasErrors('items');
    }

    public function test_screens_render_and_need_permission(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['action' => 'confirm']));
        $document = StockDocument::first();

        $this->actingAs($this->keeper)->get(route('inventory.stock.index', ['store' => $this->food->id]))->assertOk()->assertSee($this->rice->name);
        $this->actingAs($this->keeper)->get(route('inventory.stock.card', [$this->food->id, $this->rice->id]))->assertOk()->assertSee($document->number);
        $this->actingAs($this->keeper)->get(route('inventory.stock-in.index'))->assertOk()->assertSee($document->number);
        $this->actingAs($this->keeper)->get(route('inventory.stock-in.create'))->assertOk();
        $this->actingAs($this->keeper)->get(route('inventory.stock-in.show', $document))->assertOk()->assertSee('DN-100');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('inventory.stock.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('inventory.stock-in.create'))->assertForbidden();
    }

    public function test_stock_records_protect_the_store_item_and_supplier(): void
    {
        $this->actingAs($this->keeper)->post(route('inventory.stock-in.store'), $this->payload(['action' => 'confirm']));

        $this->assertTrue($this->food->isInUse());
        $this->assertTrue($this->rice->isInUse());
        $this->assertTrue($this->supplier->isInUse());
        $this->assertFalse($this->building->isInUse());
    }
}
