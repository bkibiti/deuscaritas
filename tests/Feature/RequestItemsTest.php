<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Store;
use App\Models\Inventory\Unit;
use App\Models\RequestCategory;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A request carries item lines like the paper form: particular, quantity, rate, amount.
 * The particular is always chosen from the inventory items.
 */
class RequestItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $schoolHead;

    private Department $department;

    private RequestCategory $category;

    private Item $biscuits;

    private Item $soda;

    private Item $maandazi;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->schoolHead, $coordinator, $manager, $director, $this->requester] = User::factory()->count(5)->create()->all();

        $school = School::create(['name' => 'Primary', 'is_active' => true]);
        $this->department = Department::create(['name' => 'Kitchen', 'school_id' => $school->id, 'is_active' => true]);
        $this->category = RequestCategory::where('name', 'Other')->first();

        foreach ([$this->schoolHead, $this->requester] as $user) {
            $user->forceFill(['school_id' => $school->id])->save();
        }
        $this->schoolHead->assignRole('Head of School');
        $coordinator->assignRole('School Coordinator');
        $manager->assignRole('Manager');
        $director->assignRole('Director');

        $carton = Unit::where('abbreviation', 'ctn')->value('id');
        $piece = Unit::where('abbreviation', 'pc')->value('id');

        $this->biscuits = Item::create(['unit_id' => $carton, 'name' => 'Azam Marie biscuits', 'is_active' => true]);
        $this->soda = Item::create(['unit_id' => $carton, 'name' => 'Pepsi and Mirinda', 'is_active' => true]);
        $this->maandazi = Item::create(['unit_id' => $piece, 'name' => 'Maandazi madogo', 'is_active' => true]);

        // The stores hold some of each, so all three can be requested.
        foreach ([$this->biscuits, $this->soda, $this->maandazi] as $item) {
            $this->stock($item, 40);
        }
    }

    private function stock(Item $item, float $quantity, string $storeCode = 'FOOD'): void
    {
        DB::table('inv_stock_balances')->updateOrInsert(
            ['store_id' => Store::where('code', $storeCode)->value('id'), 'item_id' => $item->id],
            ['quantity' => $quantity, 'value' => $quantity * 1000],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $items, array $overrides = []): array
    {
        return $overrides + [
            'department_id' => $this->department->id,
            'request_category_id' => $this->category->id,
            'title' => 'Refreshments for the parents meeting',
            'description' => 'Snacks and drinks for 100 parents.',
            'items' => $items,
            'action' => 'submit',
        ];
    }

    public function test_a_request_saves_its_lines_without_prices_or_a_cost(): void
    {
        $this->actingAs($this->requester)->post(route('requests.store'), $this->form([
            ['item_id' => $this->biscuits->id, 'quantity' => 4],
            ['item_id' => $this->soda->id, 'quantity' => 4],
            ['item_id' => $this->maandazi->id, 'quantity' => 100],
        ], ['estimated_cost' => 1]))->assertSessionHasNoErrors();

        $request = SchoolRequest::first();
        $lines = $request->items;

        $this->assertCount(3, $lines);
        $this->assertSame([1, 2, 3], $lines->pluck('position')->all());
        $this->assertSame([4.0, 4.0, 100.0], $lines->map(fn ($line) => (float) $line->quantity)->all());
        $this->assertNull($lines[0]->unit_price, 'Items come from the store, so a rate sent anyway is ignored.');
        $this->assertNull($lines[0]->amount);
        $this->assertNull($request->estimated_cost);
    }

    public function test_the_form_shows_no_rate_amount_or_cost(): void
    {
        $this->actingAs($this->requester)->get(route('requests.create'))
            ->assertOk()->assertDontSee('Rate (TZS)')->assertDontSee('Amount (TZS)')->assertDontSee('Estimated cost');
    }

    public function test_a_line_must_be_an_existing_active_item(): void
    {
        $this->maandazi->update(['is_active' => false]);

        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form([['item_id' => 999999, 'quantity' => 1]]))
            ->assertSessionHasErrors('items.0.item_id');
        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form([['item_id' => $this->maandazi->id, 'quantity' => 1]]))
            ->assertSessionHasErrors('items.0.item_id');
        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form([['item_id' => '', 'name' => 'Typed by hand', 'quantity' => 1]]))
            ->assertSessionHasErrors('items.0.item_id');

        $this->assertSame(0, SchoolRequest::count());
    }

    public function test_a_line_needs_a_quantity_above_zero_and_an_item_appears_once(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form([['item_id' => $this->biscuits->id, 'quantity' => 0]]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form([
                ['item_id' => $this->biscuits->id, 'quantity' => 1],
                ['item_id' => $this->biscuits->id, 'quantity' => 2],
            ]))
            ->assertSessionHasErrors('items.0.item_id');
    }

    public function test_editing_replaces_the_lines(): void
    {
        $this->actingAs($this->requester)->post(route('requests.store'), $this->form([
            ['item_id' => $this->biscuits->id, 'quantity' => 4],
            ['item_id' => $this->soda->id, 'quantity' => 4],
        ], ['action' => 'draft']));
        $request = SchoolRequest::first();

        $this->actingAs($this->requester)->get(route('requests.edit', $request))->assertOk()->assertSee('Azam Marie biscuits');

        $this->actingAs($this->requester)->put(route('requests.update', $request), $this->form([
            ['item_id' => $this->maandazi->id, 'quantity' => 100],
        ], ['action' => 'draft']))->assertSessionHasNoErrors();

        $request = $request->fresh();
        $this->assertSame([$this->maandazi->id], $request->items->pluck('item_id')->all());
    }

    public function test_the_request_page_lists_the_lines_and_the_signature_covers_them(): void
    {
        $this->actingAs($this->requester)->post(route('requests.store'), $this->form([
            ['item_id' => $this->biscuits->id, 'quantity' => 4],
        ]));
        $request = SchoolRequest::first();

        $this->actingAs($this->requester)->get(route('requests.show', $request))
            ->assertOk()->assertSee('Azam Marie biscuits')->assertDontSee('Rate (TZS)');

        $this->actingAs($this->schoolHead)->post(route('requests.approve', $request), ['password' => 'password'])->assertSessionHasNoErrors();

        $signedHash = $request->approvals()->where('step_key', 'school_head')->value('content_hash');
        $this->assertSame($signedHash, $request->fresh()->contentHash());

        $request->items()->first()->update(['quantity' => 40]);
        $this->assertNotSame($signedHash, $request->fresh()->contentHash(), 'Changing a line after signing no longer matches the signature.');
    }

    public function test_the_form_offers_only_active_items_grouped_by_category(): void
    {
        $this->maandazi->update(['is_active' => false]);
        $this->soda->update(['item_category_id' => null]);
        $this->biscuits->update(['item_category_id' => ItemCategory::where('name', 'Food Stock')->value('id')]);

        $this->actingAs($this->requester)->get(route('requests.create'))
            ->assertOk()
            ->assertSee('<optgroup label="Food Stock">', false)
            ->assertSee('<optgroup label="Other items">', false)
            ->assertSee('Azam Marie biscuits')
            ->assertDontSee('Maandazi madogo');
    }

    public function test_the_form_offers_only_items_in_stock_with_the_quantity_available(): void
    {
        $this->stock($this->biscuits, 15, 'BUILD'); // 40 in one store, 15 in another
        $this->stock($this->soda, 0);

        $this->actingAs($this->requester)->get(route('requests.create'))
            ->assertOk()
            ->assertSee('Azam Marie biscuits (55 available)')
            ->assertDontSee('Pepsi and Mirinda');
    }

    public function test_an_item_that_is_out_of_stock_cannot_be_requested(): void
    {
        $this->stock($this->soda, 0);

        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form([['item_id' => $this->soda->id, 'quantity' => 2]]))
            ->assertSessionHasErrors('items.0.item_id');
    }

    public function test_an_item_already_on_a_request_stays_when_its_stock_runs_out(): void
    {
        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form([['item_id' => $this->soda->id, 'quantity' => 2]], ['action' => 'draft']));
        $request = SchoolRequest::latest('id')->first();
        $this->stock($this->soda, 0);

        $this->actingAs($this->requester)->get(route('requests.edit', $request))
            ->assertOk()->assertSee('Pepsi and Mirinda (0 available)');
        $this->actingAs($this->requester)
            ->put(route('requests.update', $request), $this->form([['item_id' => $this->soda->id, 'quantity' => 3]], ['action' => 'draft']))
            ->assertSessionHasNoErrors();
    }

    public function test_an_item_on_a_request_cannot_be_deleted(): void
    {
        $this->actingAs($this->requester)->post(route('requests.store'), $this->form([
            ['item_id' => $this->biscuits->id, 'quantity' => 4],
        ]));
        $admin = User::factory()->create();
        $admin->givePermissionTo('Manage Inventory Items');

        // An item that has held stock cannot be removed either, so clear the one that is deleted.
        DB::table('inv_stock_balances')->where('item_id', $this->soda->id)->delete();

        $this->actingAs($admin)->delete(route('inventory.items.destroy', $this->biscuits));
        $this->actingAs($admin)->delete(route('inventory.items.destroy', $this->soda));

        $this->assertNotNull($this->biscuits->fresh());
        $this->assertNull($this->soda->fresh());
    }
}
