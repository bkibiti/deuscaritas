<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockDocument;
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
 * After the Director approves, he assigns a staff member. That person issues the items from
 * the stores and says who received them. Issuing is the step that takes the stock out.
 */
class StockOutTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $head;

    private User $coordinator;

    private User $manager;

    private User $director;

    private User $storeman;

    private Store $food;

    private Store $building;

    private Item $rice;

    private Item $beans;

    private SchoolRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::create(['name' => 'Primary', 'is_active' => true]);
        $department = Department::create(['name' => 'Kitchen', 'school_id' => $school->id, 'is_active' => true]);

        [$this->requester, $this->head, $this->coordinator, $this->manager, $this->director, $this->storeman] = User::factory()->count(6)->create()->all();
        foreach ([$this->requester, $this->head] as $user) {
            $user->forceFill(['school_id' => $school->id])->save();
        }
        $this->head->assignRole('Head of School');
        $this->coordinator->assignRole('School Coordinator');
        $this->manager->assignRole('Manager');
        $this->director->assignRole('Director');

        $this->food = Store::where('code', 'FOOD')->first();
        $this->building = Store::where('code', 'BUILD')->first();
        $kg = Unit::where('abbreviation', 'kg')->value('id');
        $this->rice = Item::create(['unit_id' => $kg, 'name' => 'Basmati test rice', 'is_active' => true]);
        $this->beans = Item::create(['unit_id' => $kg, 'name' => 'Kidney test beans', 'is_active' => true]);

        // 100 kg of rice worth 2,000 each, split across two stores; 10 kg of beans.
        $this->stock($this->food, $this->rice, 60, 120000);
        $this->stock($this->building, $this->rice, 40, 80000);
        $this->stock($this->food, $this->beans, 10, 30000);

        $this->actingAs($this->requester)->post(route('requests.store'), [
            'department_id' => $department->id,
            'request_category_id' => RequestCategory::where('name', 'Other')->value('id'),
            'title' => 'Food for the term',
            'description' => 'Rice and beans.',
            'items' => [
                ['item_id' => $this->rice->id, 'quantity' => 50],
                ['item_id' => $this->beans->id, 'quantity' => 8],
            ],
            'action' => 'submit',
        ])->assertSessionHasNoErrors();
        $this->request = SchoolRequest::first();
    }

    private function stock(Store $store, Item $item, float $quantity, float $value): void
    {
        DB::table('inv_stock_balances')->updateOrInsert(['store_id' => $store->id, 'item_id' => $item->id], ['quantity' => $quantity, 'value' => $value]);
    }

    private function balance(Store $store, Item $item): float
    {
        return (float) DB::table('inv_stock_balances')->where(['store_id' => $store->id, 'item_id' => $item->id])->value('quantity');
    }

    private function approveAll(): void
    {
        foreach ([$this->head, $this->coordinator, $this->manager, $this->director] as $approver) {
            $this->actingAs($approver)->post(route('requests.approve', $this->request), ['password' => 'password'])->assertSessionHasNoErrors();
        }
    }

    private function assignToStoreman(): void
    {
        $this->approveAll();
        $this->actingAs($this->director)->post(route('requests.assign', $this->request), ['assigned_to' => $this->storeman->id])->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function issueForm(array $overrides = []): array
    {
        $lines = $this->request->items->keyBy('item_id');

        return $overrides + [
            'received_by' => $this->requester->id,
            'confirm' => 1,
            'lines' => [
                $lines[$this->rice->id]->id => ['store_id' => $this->food->id, 'quantity' => 50],
                $lines[$this->beans->id]->id => ['store_id' => $this->food->id, 'quantity' => 8],
            ],
        ];
    }

    public function test_the_director_who_approved_can_assign_the_request_to_a_staff_member(): void
    {
        $this->approveAll();

        $this->actingAs($this->director)->get(route('requests.show', $this->request))->assertSee('Assign for implementation');
        $this->actingAs($this->director)->post(route('requests.assign', $this->request), ['assigned_to' => $this->storeman->id])->assertSessionHasNoErrors();

        $this->assertSame($this->storeman->id, $this->request->fresh()->assigned_to);
        $this->assertSame(SchoolRequest::STATUS_IN_PROGRESS, $this->request->fresh()->status);
        $this->actingAs($this->manager)->post(route('requests.assign', $this->request), ['assigned_to' => $this->storeman->id])->assertForbidden();
    }

    public function test_only_the_assigned_person_can_issue_the_items(): void
    {
        $this->assignToStoreman();

        $this->actingAs($this->requester)->post(route('requests.issue', $this->request), $this->issueForm())->assertForbidden();
        $this->actingAs($this->director)->post(route('requests.issue', $this->request), $this->issueForm())->assertForbidden();
        $this->assertSame(60.0, $this->balance($this->food, $this->rice));
    }

    public function test_the_assignee_sees_an_issue_button_and_the_issue_page_lists_the_stores_holding_each_item(): void
    {
        $this->assignToStoreman();

        $this->actingAs($this->storeman)->get(route('requests.show', $this->request))
            ->assertOk()->assertSee('Issue the items')->assertSee(route('requests.issue.create', $this->request))
            ->assertDontSee('Mark as completed');
        $this->actingAs($this->storeman)->get(route('requests.issue.create', $this->request))
            ->assertOk()->assertSee('Received by')
            ->assertSee($this->food->name.' (60 available)')->assertSee($this->building->name.' (40 available)');
        $this->actingAs($this->storeman)->post(route('requests.complete', $this->request))->assertForbidden();
    }

    public function test_only_the_assignee_can_open_the_issue_page(): void
    {
        $this->assignToStoreman();

        $this->actingAs($this->requester)->get(route('requests.issue.create', $this->request))->assertForbidden();
        $this->actingAs($this->director)->get(route('requests.issue.create', $this->request))->assertForbidden();
    }

    public function test_the_confirmation_is_required_and_the_note_is_kept(): void
    {
        $this->assignToStoreman();

        $this->actingAs($this->storeman)->from(route('requests.issue.create', $this->request))->post(route('requests.issue', $this->request), $this->issueForm(['confirm' => null]))
            ->assertRedirect(route('requests.issue.create', $this->request))->assertSessionHasErrors('confirm', null, 'issue');
        $this->assertSame(60.0, $this->balance($this->food, $this->rice));

        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm(['note' => 'Collected at the gate']))->assertSessionHasNoErrors();
        $this->assertSame('Collected at the gate', StockDocument::where('request_id', $this->request->id)->value('notes'));
    }

    public function test_the_issue_note_can_be_printed_by_those_who_can_see_the_request(): void
    {
        $this->assignToStoreman();
        $beansLine = $this->request->items->firstWhere('item_id', $this->beans->id);
        $riceLine = $this->request->items->firstWhere('item_id', $this->rice->id);
        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), [
            'received_by' => $this->requester->id,
            'confirm' => 1,
            'lines' => [
                $riceLine->id => ['store_id' => $this->food->id, 'quantity' => 50],
                $beansLine->id => ['store_id' => $this->food->id, 'quantity' => 6],
            ],
        ])->assertSessionHasNoErrors();
        $document = StockDocument::where('request_id', $this->request->id)->first();

        $this->actingAs($this->requester)->get(route('requests.show', $this->request))
            ->assertSee('Print issue note')->assertSee(route('requests.issue-note', [$this->request, $document]));
        $this->actingAs($this->requester)->get(route('requests.issue-note', [$this->request, $document]))
            ->assertOk()->assertSee('Stock issue note')->assertSee($document->number)->assertSee('Not fully issued')
            ->assertSee('Kidney test beans (2 kg short)')->assertSee('Received by')->assertSee('window.print()', false);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('requests.issue-note', [$this->request, $document]))->assertForbidden();
    }

    public function test_issuing_takes_the_stock_out_records_the_receiver_and_completes_the_request(): void
    {
        $this->assignToStoreman();
        $receiver = User::factory()->create(['name' => 'Mama Lishe']);

        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm(['received_by' => $receiver->id]))
            ->assertSessionHasNoErrors()->assertRedirect(route('requests.show', $this->request));

        $this->assertSame(10.0, $this->balance($this->food, $this->rice));
        $this->assertSame(2.0, $this->balance($this->food, $this->beans));

        $request = $this->request->fresh();
        $this->assertSame(SchoolRequest::STATUS_COMPLETED, $request->status);
        $this->assertSame([50.0, 8.0], $request->items->map(fn ($line) => (float) $line->issued_quantity)->all());

        $document = StockDocument::where('request_id', $request->id)->first();
        $this->assertSame(StockDocument::TYPE_ISSUE, $document->type);
        $this->assertSame($receiver->id, $document->received_by);
        $this->assertSame($this->storeman->id, $document->created_by);
        $this->assertStringStartsWith('SOUT-', $document->number);

        // Rice leaves at the store's average cost (2,000), so its value falls with the quantity.
        $this->assertEquals(-50, DB::table('inv_stock_ledger')->where('document_id', $document->id)->where('item_id', $this->rice->id)->value('quantity'));
        $this->assertEquals(20000, DB::table('inv_stock_balances')->where(['store_id' => $this->food->id, 'item_id' => $this->rice->id])->value('value'));

        $this->actingAs($this->requester)->get(route('requests.show', $request))
            ->assertSee('Issued from stock')->assertSee('Mama Lishe')->assertSee($document->number);
    }

    public function test_items_taken_from_different_stores_get_one_issue_note_per_store(): void
    {
        $this->assignToStoreman();
        $riceLine = $this->request->items->firstWhere('item_id', $this->rice->id);
        $beansLine = $this->request->items->firstWhere('item_id', $this->beans->id);

        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), [
            'received_by' => $this->requester->id,
            'confirm' => 1,
            'lines' => [
                $riceLine->id => ['store_id' => $this->building->id, 'quantity' => 40],
                $beansLine->id => ['store_id' => $this->food->id, 'quantity' => 8],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, $this->balance($this->building, $this->rice));
        $this->assertEquals(0, DB::table('inv_stock_balances')->where(['store_id' => $this->building->id, 'item_id' => $this->rice->id])->value('value'), 'Taking out the last leaves no value behind.');
        $this->assertSame(2, StockDocument::where('request_id', $this->request->id)->count());
    }

    public function test_it_cannot_issue_more_than_asked_or_more_than_the_store_holds(): void
    {
        $this->assignToStoreman();
        $beansLine = $this->request->items->firstWhere('item_id', $this->beans->id);

        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), [
            'received_by' => $this->requester->id,
            'confirm' => 1,
            'lines' => [$beansLine->id => ['store_id' => $this->food->id, 'quantity' => 9]],
        ])->assertSessionHasErrors('issue', null, 'issue');

        $this->stock($this->food, $this->beans, 5, 15000);
        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), [
            'received_by' => $this->requester->id,
            'confirm' => 1,
            'lines' => [$beansLine->id => ['store_id' => $this->food->id, 'quantity' => 8]],
        ])->assertSessionHasErrors('issue', null, 'issue');

        $this->assertSame(5.0, $this->balance($this->food, $this->beans));
        $this->assertSame(SchoolRequest::STATUS_IN_PROGRESS, $this->request->fresh()->status);
        $this->assertSame(0, StockDocument::where('request_id', $this->request->id)->count(), 'A refused issue leaves nothing behind.');
    }

    public function test_a_request_cannot_be_issued_twice(): void
    {
        $this->assignToStoreman();

        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm())->assertSessionHasNoErrors();
        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm())->assertForbidden();

        $this->assertSame(10.0, $this->balance($this->food, $this->rice));
    }

    public function test_the_receiver_and_a_store_are_required(): void
    {
        $this->assignToStoreman();

        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm(['received_by' => '']))
            ->assertSessionHasErrors('received_by', null, 'issue');

        $riceLine = $this->request->items->firstWhere('item_id', $this->rice->id);
        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), [
            'received_by' => $this->requester->id,
            'confirm' => 1,
            'lines' => [$riceLine->id => ['store_id' => '', 'quantity' => 5]],
        ])->assertSessionHasErrors('issue', null, 'issue');
    }

    public function test_issue_documents_stay_out_of_the_stock_in_list_and_show_on_the_stock_card(): void
    {
        $this->assignToStoreman();
        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm())->assertSessionHasNoErrors();
        $document = StockDocument::where('request_id', $this->request->id)->first();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('View Stock');

        $this->actingAs($viewer)->get(route('inventory.stock-in.index'))->assertDontSee($document->number);
        $this->actingAs($viewer)->get(route('inventory.stock-in.show', $document))->assertNotFound();
        $this->actingAs($viewer)->get(route('inventory.stock.card', [$this->food, $this->rice]))->assertOk()->assertSee($document->number)->assertSee('Issue');
    }

    public function test_a_request_without_items_is_still_completed_with_a_note(): void
    {
        $this->request->items()->delete();
        $this->assignToStoreman();

        $this->actingAs($this->storeman)->get(route('requests.show', $this->request))->assertSee('Mark as completed')->assertDontSee('Issue the items');
        $this->actingAs($this->storeman)->post(route('requests.complete', $this->request), ['note' => 'Done'])->assertSessionHasNoErrors();
        $this->assertSame(SchoolRequest::STATUS_COMPLETED, $this->request->fresh()->status);
    }

    public function test_the_assigned_person_finds_the_assignment_in_the_menu_the_dashboard_the_list_and_the_bell(): void
    {
        $this->assignToStoreman();

        $this->actingAs($this->storeman)->get(route('requests.index', ['scope' => 'assigned']))
            ->assertOk()->assertSee('Assigned to Me')->assertSee('Food for the term');
        $this->actingAs($this->storeman)->get(route('home'))
            ->assertSee('Assigned to you')->assertSee('Issue items')->assertSee('1 request is assigned to you');
        $this->actingAs($this->requester)->get(route('requests.index', ['scope' => 'assigned']))
            ->assertOk()->assertDontSee('Food for the term');

        // Once issued it leaves the list.
        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm())->assertSessionHasNoErrors();
        $this->actingAs($this->storeman)->get(route('requests.index', ['scope' => 'assigned']))->assertDontSee('Food for the term');
        $this->actingAs($this->storeman)->get(route('home'))->assertDontSee('Assigned to you');
    }

    public function test_a_long_timeline_shows_the_latest_entries_and_folds_the_earlier_ones(): void
    {
        $this->assignToStoreman();
        $this->actingAs($this->storeman)->post(route('requests.issue', $this->request), $this->issueForm())->assertSessionHasNoErrors();

        $this->actingAs($this->requester)->get(route('requests.show', $this->request))
            ->assertOk()->assertSee('timeline-earlier')->assertSee('earlier</button>', false);
    }
}
