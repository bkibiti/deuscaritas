<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Inventory\Store;
use App\Models\RequestCategory;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard shows each person what they can use: staff their own requests, signers and
 * overseers the overview, stock keepers the stock. Figures come from the real tables.
 */
class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $staff;

    private User $head;

    private User $manager;

    private User $director;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Primary', 'is_active' => true]);
        $this->department = Department::create(['name' => 'Kitchen', 'school_id' => $this->school->id, 'is_active' => true]);

        [$this->staff, $this->head, $coordinator, $this->manager, $this->director] = User::factory()->count(5)->create()->all();
        foreach ([$this->staff, $this->head] as $user) {
            $user->forceFill(['school_id' => $this->school->id])->save();
        }
        $this->head->assignRole('Head of School');
        $coordinator->assignRole('School Coordinator');
        $this->manager->assignRole('Manager');
        $this->director->assignRole('Director');
        $this->director->givePermissionTo('View All Requests');
    }

    private function raise(User $by, string $title, string $action = 'submit'): SchoolRequest
    {
        $this->actingAs($by)->post(route('requests.store'), [
            'department_id' => $this->department->id,
            'request_category_id' => RequestCategory::where('name', 'Other')->value('id'),
            'title' => $title,
            'description' => 'Needed this term.',
            'action' => $action,
        ])->assertSessionHasNoErrors();

        return SchoolRequest::latest('id')->first();
    }

    public function test_staff_without_a_signing_role_see_only_their_own_requests(): void
    {
        $mine = $this->raise($this->staff, 'My flour');
        $other = User::factory()->create(['school_id' => $this->school->id]);
        $this->raise($other, 'Someone else request');

        $response = $this->actingAs($this->staff)->get(route('home'))->assertOk();

        $this->assertFalse($response->viewData('overview'));
        $response->assertSee('My flour')
            ->assertDontSee('Someone else request')
            ->assertDontSee('Waiting for your signature')
            ->assertDontSee('Recent stock movements');
        $this->assertSame(['Open requests', 'Returned to me', 'Approved', 'Completed'], collect($response->viewData('tiles'))->pluck('label')->all());
        $this->assertSame(1, $response->viewData('tiles')[0]['value']);
    }

    public function test_a_returned_request_is_called_out_to_its_owner(): void
    {
        $request = $this->raise($this->staff, 'Chalk');
        $this->actingAs($this->head)->post(route('requests.return', $request), ['password' => 'password', 'comment' => 'Add the quantity'])->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get(route('home'))
            ->assertSee('was returned to you')->assertSee('Change and resend');
    }

    public function test_a_signer_sees_what_waits_for_them_and_where_requests_are(): void
    {
        $first = $this->raise($this->staff, 'Cleaning supplies');
        $this->raise($this->staff, 'Exercise books');

        $response = $this->actingAs($this->head)->get(route('home'))->assertOk();

        $this->assertTrue($response->viewData('overview'));
        $response->assertSee('Waiting for your signature')->assertSee('Cleaning supplies')->assertSee('Exercise books');
        $tiles = collect($response->viewData('tiles'))->pluck('value', 'label');
        $this->assertSame(2, $tiles['Waiting for my signature']);

        $pipeline = collect($response->viewData('pipeline'))->pluck('count', 'name');
        $this->assertSame(2, $pipeline['Head of School']);
        $this->assertSame(0, $pipeline['Manager']);

        $this->actingAs($this->head)->post(route('requests.approve', $first), ['password' => 'password'])->assertSessionHasNoErrors();
        $pipeline = collect($this->actingAs($this->head)->get(route('home'))->viewData('pipeline'))->pluck('count', 'name');
        $this->assertSame(1, $pipeline['Head of School']);
        $this->assertSame(1, $pipeline['School Coordinator']);
    }

    public function test_the_month_chart_counts_raised_and_fully_approved(): void
    {
        $request = $this->raise($this->staff, 'Soap');
        $this->raise($this->staff, 'Brooms');
        SchoolRequest::whereKey($request->id)->update(['status' => SchoolRequest::STATUS_APPROVED, 'decided_at' => now()]);

        $months = $this->actingAs($this->director)->get(route('home'))->viewData('months');
        $this->assertCount(6, $months);
        $this->assertSame(['raised' => 2, 'approved' => 1], collect(end($months))->only(['raised', 'approved'])->all());

        $this->assertCount(1, $this->actingAs($this->director)->get(route('home', ['period' => 'month']))->viewData('months'));
    }

    public function test_requests_are_counted_by_department(): void
    {
        $this->raise($this->staff, 'One');
        $this->raise($this->staff, 'Two');

        $departments = $this->actingAs($this->director)->get(route('home'))->viewData('departments');
        $this->assertSame([['name' => 'Kitchen', 'count' => 2]], $departments);
    }

    public function test_head_office_can_narrow_the_figures_to_one_school(): void
    {
        $this->raise($this->staff, 'Primary request');
        $other = School::create(['name' => 'Secondary', 'is_active' => true]);
        DB::table('requests')->where('title', 'Primary request')->update(['school_id' => $other->id]);
        $this->raise($this->staff, 'Another primary request');

        $all = $this->actingAs($this->director)->get(route('home'))->viewData('departments');
        $this->assertSame(2, $all[0]['count']);

        $one = $this->actingAs($this->director)->get(route('home', ['school' => $this->school->id]))->viewData('departments');
        $this->assertSame(1, $one[0]['count']);

        // A user who belongs to a school cannot widen it by asking for another.
        $own = $this->actingAs($this->head)->get(route('home', ['school' => $other->id]));
        $this->assertNull($own->viewData('schoolId'));
    }

    public function test_stock_sections_need_the_view_stock_permission(): void
    {
        $this->actingAs($this->manager)->get(route('home'))
            ->assertDontSee('Recent stock movements')->assertDontSee('Stock by store');

        $this->manager->givePermissionTo('View Stock');
        $store = Store::where('code', 'FOOD')->first();
        $this->actingAs($this->manager)->get(route('home'))
            ->assertSee('Stock by store')->assertSee($store->name)->assertSee('Recent stock movements')
            ->assertSee('No stock has been received yet.');
    }

    public function test_no_money_is_shown_on_the_dashboard(): void
    {
        $this->manager->givePermissionTo('View Stock');

        $this->actingAs($this->manager)->get(route('home'))->assertDontSee('TZS');
    }
}
