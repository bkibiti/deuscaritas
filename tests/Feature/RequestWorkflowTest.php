<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RequestApproval;
use App\Models\RequestCategory;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The request form and the approval chain, end to end through the HTTP routes.
 */
class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $schoolHead;

    private User $coordinator;

    private User $manager;

    private User $director;

    private School $school;

    private Department $department;

    private RequestCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Primary', 'is_active' => true]);
        $this->department = Department::create(['name' => 'Science', 'school_id' => $this->school->id, 'is_active' => true]);
        $this->category = RequestCategory::where('name', 'Maintenance')->first();

        [$this->schoolHead, $this->coordinator, $this->manager, $this->director, $this->requester] = User::factory()->count(5)->create()->all();

        $this->give($this->schoolHead, 'Head of School', $this->school);
        $this->give($this->coordinator, 'School Coordinator');
        $this->give($this->manager, 'Manager');
        $this->give($this->director, 'Director');
        $this->give($this->requester, null, $this->school);
    }

    /**
     * Give a user a role and/or a school. The approval chain names roles, so this is all it takes
     * to make someone an approver.
     */
    private function give(User $user, ?string $role, ?School $school = null): void
    {
        if ($school) {
            $user->forceFill(['school_id' => $school->id])->save();
        }

        if ($role) {
            $user->assignRole($role);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'department_id' => $this->department->id,
            'request_category_id' => $this->category->id,
            'title' => 'Classroom projector not working',
            'justification' => 'Examination preparation is tomorrow.',
            'required_date' => '2026-12-01',
            'quantity' => 1,
            'suggested_priority' => 'urgent',
            'action' => 'submit',
        ];
    }

    private function submitRequest(array $overrides = [], ?User $as = null): SchoolRequest
    {
        $this->actingAs($as ?? $this->requester)->post(route('requests.store'), $this->form($overrides))->assertSessionHasNoErrors();

        return SchoolRequest::latest('id')->first();
    }

    private function approveAs(User $user, SchoolRequest $request): void
    {
        $this->actingAs($user)
            ->post(route('requests.approve', $request), ['password' => 'password', 'comment' => 'OK'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('requests.show', $request));
    }

    public function test_a_draft_is_saved_without_a_number_or_approvals(): void
    {
        $response = $this->actingAs($this->requester)->post(route('requests.store'), $this->form(['action' => 'draft']));

        $request = SchoolRequest::first();
        $response->assertRedirect(route('requests.show', $request));
        $this->assertSame(SchoolRequest::STATUS_DRAFT, $request->status);
        $this->assertNull($request->number);
        $this->assertSame($this->school->id, $request->school_id);
        $this->assertCount(0, $request->approvals);
    }

    public function test_submitting_gives_a_number_and_starts_at_the_head_of_school(): void
    {
        $request = $this->submitRequest();

        $this->assertSame('REQ-'.now('Africa/Dar_es_Salaam')->year.'-000001', $request->number);
        $this->assertSame(SchoolRequest::STATUS_PENDING, $request->status);
        $this->assertSame('Awaiting Head of School', $request->statusLabel());
        $this->assertTrue($request->isAwaiting($this->schoolHead));
        $this->assertSame(
            ['school_head', 'coordinator', 'manager', 'director'],
            $request->approvals->pluck('step_key')->all(),
            'The Director signs every request.'
        );
    }

    public function test_request_numbers_run_in_sequence(): void
    {
        $first = $this->submitRequest();
        $second = $this->submitRequest();

        $this->assertStringEndsWith('-000001', $first->number);
        $this->assertStringEndsWith('-000002', $second->number);
    }

    public function test_the_full_chain_approves_the_request_step_by_step(): void
    {
        $request = $this->submitRequest();

        foreach ([$this->schoolHead, $this->coordinator, $this->manager] as $approver) {
            $this->approveAs($approver, $request);
            $this->assertSame(SchoolRequest::STATUS_PENDING, $request->fresh()->status);
        }

        $this->assertSame('Awaiting Director', $request->fresh()->statusLabel());

        $this->approveAs($this->director, $request);

        $request = $request->fresh();
        $this->assertSame(SchoolRequest::STATUS_APPROVED, $request->status);
        $this->assertNotNull($request->decided_at);
        $this->assertSame(4, $request->approvals->where('status', RequestApproval::STATUS_APPROVED)->count());
        $this->assertSame(
            ['submitted', 'approved', 'approved', 'approved', 'approved', 'fully_approved'],
            $request->actions->pluck('action')->all()
        );
    }

    public function test_a_signature_stores_who_when_and_a_hash_of_the_request(): void
    {
        $request = $this->submitRequest();

        $this->approveAs($this->schoolHead, $request);

        $approval = $request->approvals()->where('step_key', 'school_head')->first();
        $this->assertSame($this->schoolHead->id, $approval->acted_by);
        $this->assertNotNull($approval->acted_at);
        $this->assertSame('OK', $approval->comment);
        $this->assertSame($request->fresh()->contentHash(), $approval->content_hash);
    }

    public function test_the_director_signs_last_on_every_request(): void
    {
        $request = $this->submitRequest();

        $this->assertSame('director', $request->approvals->last()->step_key);
        $this->assertSame(Role::where('name', 'Director')->value('id'), $request->approvals->last()->role_id);

        foreach ([$this->schoolHead, $this->coordinator, $this->manager] as $approver) {
            $this->approveAs($approver, $request);
        }
        $this->assertSame('Awaiting Director', $request->fresh()->statusLabel());

        $this->approveAs($this->director, $request);
        $this->assertSame(SchoolRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_requests_are_not_priced_so_no_cost_is_taken(): void
    {
        $request = $this->submitRequest(['estimated_cost' => 250000]);

        $this->assertNull($request->estimated_cost, 'Requests are for stock items, so no cost is taken.');
    }

    public function test_approving_needs_the_approvers_password(): void
    {
        $request = $this->submitRequest();

        $response = $this->actingAs($this->schoolHead)->post(route('requests.approve', $request), ['password' => 'wrong']);

        $response->assertSessionHasErrorsIn('approve', ['password']);
        $this->assertTrue($request->fresh()->isAwaiting($this->schoolHead), 'A wrong password leaves the step waiting.');
    }

    public function test_only_the_current_approver_can_act(): void
    {
        $request = $this->submitRequest();

        // The Manager's turn has not come, and the requester never signs.
        $this->actingAs($this->manager)->post(route('requests.approve', $request), ['password' => 'password'])->assertForbidden();
        $this->actingAs($this->requester)->post(route('requests.approve', $request), ['password' => 'password'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('requests.reject', $request), ['password' => 'password', 'comment' => 'No'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('requests.return', $request), ['comment' => 'Fix'])->assertForbidden();

        $this->assertSame(SchoolRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_rejecting_needs_a_reason_and_ends_the_request(): void
    {
        $request = $this->submitRequest();

        $this->actingAs($this->schoolHead)
            ->post(route('requests.reject', $request), ['password' => 'password'])
            ->assertSessionHasErrorsIn('reject', ['comment']);

        $this->actingAs($this->schoolHead)
            ->post(route('requests.reject', $request), ['password' => 'password', 'comment' => 'Not in the budget.'])
            ->assertSessionHasNoErrors();

        $request = $request->fresh();
        $this->assertSame(SchoolRequest::STATUS_REJECTED, $request->status);
        $this->assertSame('Not in the budget.', $request->approvals->first()->comment);
        $this->assertNull($request->currentApproval);

        $this->actingAs($this->schoolHead)->post(route('requests.approve', $request), ['password' => 'password'])->assertForbidden();
    }

    public function test_a_returned_request_is_corrected_and_starts_a_new_round_with_the_same_number(): void
    {
        $request = $this->submitRequest();
        $this->approveAs($this->schoolHead, $request);

        $this->actingAs($this->coordinator)
            ->post(route('requests.return', $request), ['comment' => 'Add a quotation.'])
            ->assertSessionHasNoErrors();

        $request = $request->fresh();
        $number = $request->number;
        $this->assertSame(SchoolRequest::STATUS_RETURNED, $request->status);
        $this->assertTrue($request->canBeEditedBy($this->requester));

        $this->actingAs($this->requester)
            ->put(route('requests.update', $request), $this->form(['title' => 'Projector, with quotation']))
            ->assertSessionHasNoErrors();

        $request = $request->fresh();
        $this->assertSame($number, $request->number);
        $this->assertSame(SchoolRequest::STATUS_PENDING, $request->status);
        $this->assertSame('Projector, with quotation', $request->title);
        $this->assertSame(2, $request->approvals->max('round'));
        $this->assertSame('school_head', $request->currentApproval->step_key, 'A corrected request starts the chain again.');
        $this->assertContains('resubmitted', $request->actions->pluck('action'));
    }

    public function test_a_pending_request_cannot_be_edited(): void
    {
        $request = $this->submitRequest();

        $this->actingAs($this->requester)->get(route('requests.edit', $request))->assertForbidden();
        $this->actingAs($this->requester)->put(route('requests.update', $request), $this->form(['title' => 'Changed']))->assertForbidden();
        $this->assertSame('Classroom projector not working', $request->fresh()->title);
    }

    public function test_a_step_the_requester_holds_is_skipped(): void
    {
        $request = $this->submitRequest([], $this->schoolHead);

        $first = $request->approvals->first();
        $this->assertSame(RequestApproval::STATUS_SKIPPED, $first->status);
        $this->assertSame('coordinator', $request->currentApproval->step_key);
    }

    public function test_head_office_staff_choose_the_school_and_its_head_signs(): void
    {
        $other = School::create(['name' => 'Secondary', 'is_active' => true]);
        $otherHead = User::factory()->create();
        $this->give($otherHead, 'Head of School', $other);

        $officer = User::factory()->create(); // no school
        $ict = Department::create(['name' => 'ICT', 'school_id' => null, 'is_active' => true]);

        $this->actingAs($officer)->post(route('requests.store'), $this->form(['department_id' => $ict->id]))->assertSessionHasErrors('school_id');

        $request = $this->submitRequest(['department_id' => $ict->id, 'school_id' => $other->id], $officer);

        $this->assertSame($other->id, $request->school_id);
        $this->assertTrue($request->isAwaiting($otherHead));
        $this->assertFalse($request->isAwaiting($this->schoolHead));
    }

    public function test_a_staff_member_raises_requests_for_their_own_school_whatever_the_form_says(): void
    {
        $other = School::create(['name' => 'Secondary', 'is_active' => true]);

        $request = $this->submitRequest(['school_id' => $other->id]);

        $this->assertSame($this->school->id, $request->school_id);
    }

    public function test_a_school_head_signs_only_for_their_own_school(): void
    {
        $other = School::create(['name' => 'Secondary', 'is_active' => true]);
        $otherHead = User::factory()->create();
        $this->give($otherHead, 'Head of School', $other);

        $request = $this->submitRequest();

        $this->actingAs($otherHead)->post(route('requests.approve', $request), ['password' => 'password'])->assertForbidden();
        $this->actingAs($otherHead)->get(route('requests.show', $request))->assertForbidden();
        $this->assertSame(0, SchoolRequest::awaitingActionBy($otherHead)->count());
        $this->assertSame(1, SchoolRequest::awaitingActionBy($this->schoolHead)->count());
    }

    public function test_any_holder_of_a_role_can_sign_and_the_signer_is_recorded(): void
    {
        $deputy = User::factory()->create();
        $this->give($deputy, 'Head of School', $this->school);
        $secondManager = User::factory()->create();
        $this->give($secondManager, 'Manager');

        $request = $this->submitRequest();

        $this->approveAs($deputy, $request);
        $this->approveAs($this->coordinator, $request);
        $this->approveAs($secondManager, $request);
        $this->approveAs($this->director, $request);

        $this->assertSame(SchoolRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame($deputy->id, $request->approvals()->where('step_key', 'school_head')->first()->acted_by);
        $this->assertSame($secondManager->id, $request->approvals()->where('step_key', 'manager')->first()->acted_by);
    }

    public function test_a_user_who_loses_the_role_can_no_longer_sign(): void
    {
        $request = $this->submitRequest();

        $this->schoolHead->removeRole('Head of School');

        $this->actingAs($this->schoolHead)->post(route('requests.approve', $request), ['password' => 'password'])->assertForbidden();
    }

    public function test_a_request_stays_a_draft_when_no_one_holds_the_school_heads_role_at_its_school(): void
    {
        $this->schoolHead->removeRole('Head of School');

        $response = $this->actingAs($this->requester)->post(route('requests.store'), $this->form());

        $request = SchoolRequest::first();
        $response->assertSessionHasErrors('approvers');
        $this->assertStringContainsString('Head of School role at Primary', session('errors')->first('approvers'));
        $this->assertSame(SchoolRequest::STATUS_DRAFT, $request->status);
    }

    public function test_a_request_stays_a_draft_when_a_step_has_nobody_to_sign_it(): void
    {
        WorkflowStep::where('key', 'manager')->update(['role_id' => null]);

        $response = $this->actingAs($this->requester)->post(route('requests.store'), $this->form());

        $request = SchoolRequest::first();
        $response->assertRedirect(route('requests.edit', $request));
        $response->assertSessionHasErrors('approvers');
        $this->assertSame(SchoolRequest::STATUS_DRAFT, $request->status);
        $this->assertNull($request->number);
        $this->assertCount(0, $request->approvals);

        $this->actingAs($this->requester)->get(route('requests.edit', $request))->assertOk();
    }

    public function test_an_inactive_step_is_left_out_of_the_chain(): void
    {
        WorkflowStep::where('key', 'coordinator')->update(['is_active' => false]);

        $this->assertSame(['school_head', 'manager', 'director'], $this->submitRequest()->approvals->pluck('step_key')->all());
    }

    public function test_a_request_needs_a_department(): void
    {
        $this->actingAs($this->requester)->post(route('requests.store'), $this->form(['department_id' => '']))->assertSessionHasErrors('department_id');
        $this->assertSame(0, SchoolRequest::count());
    }

    public function test_who_can_see_a_request(): void
    {
        $request = $this->submitRequest();
        $outsider = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('View All Requests');

        $this->actingAs($this->requester)->get(route('requests.show', $request))->assertOk();
        $this->actingAs($this->schoolHead)->get(route('requests.show', $request))->assertOk()->assertSee('Approve and sign');
        $this->actingAs($viewer)->get(route('requests.show', $request))->assertOk()->assertDontSee('Approve and sign');
        $this->actingAs($outsider)->get(route('requests.show', $request))->assertForbidden();
        // The Manager's step has not been reached yet.
        $this->actingAs($this->manager)->get(route('requests.show', $request))->assertForbidden();
    }

    public function test_the_lists_show_the_right_requests(): void
    {
        $request = $this->submitRequest();
        $this->submitRequest(['action' => 'draft', 'title' => 'Still a draft']);

        $this->actingAs($this->requester)->get(route('requests.index'))->assertOk()->assertSee($request->number)->assertSee('Still a draft');
        $this->actingAs($this->schoolHead)->get(route('requests.index', ['scope' => 'inbox']))->assertOk()->assertSee($request->number);
        $this->actingAs($this->coordinator)->get(route('requests.index', ['scope' => 'inbox']))->assertOk()->assertDontSee($request->number);
        $this->actingAs($this->schoolHead)->get(route('requests.index', ['scope' => 'all']))->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('View All Requests');
        $this->actingAs($viewer)->get(route('requests.index', ['scope' => 'all']))->assertOk()->assertSee($request->number)->assertDontSee('Still a draft');
    }

    public function test_the_requester_can_cancel_until_a_decision_is_made(): void
    {
        $request = $this->submitRequest();

        $this->actingAs($this->schoolHead)->post(route('requests.cancel', $request))->assertForbidden();
        $this->actingAs($this->requester)->post(route('requests.cancel', $request))->assertSessionHasNoErrors();
        $this->assertSame(SchoolRequest::STATUS_CANCELLED, $request->fresh()->status);

        $this->actingAs($this->schoolHead)->post(route('requests.approve', $request), ['password' => 'password'])->assertForbidden();
    }

    public function test_an_approved_request_is_assigned_then_completed_with_evidence(): void
    {
        Storage::fake('local');

        $request = $this->submitRequest();
        foreach ([$this->schoolHead, $this->coordinator, $this->manager, $this->director] as $approver) {
            $this->approveAs($approver, $request);
        }

        $technician = User::factory()->create(['name' => 'Tech Nician']);
        $this->manager->givePermissionTo('Assign Requests');

        $this->actingAs($this->requester)->post(route('requests.assign', $request), ['assigned_to' => $technician->id])->assertForbidden();
        $this->actingAs($this->manager)->post(route('requests.assign', $request), ['assigned_to' => $technician->id])->assertSessionHasNoErrors();

        $request = $request->fresh();
        $this->assertSame(SchoolRequest::STATUS_IN_PROGRESS, $request->status);
        $this->assertSame($technician->id, $request->assigned_to);
        $this->actingAs($technician)->get(route('requests.show', $request))->assertOk()->assertSee('Mark as completed');

        $this->actingAs($this->requester)->post(route('requests.complete', $request))->assertForbidden();
        $this->actingAs($technician)->post(route('requests.complete', $request), [
            'note' => 'Lamp replaced.',
            'evidence' => [UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $request = $request->fresh();
        $this->assertSame(SchoolRequest::STATUS_COMPLETED, $request->status);
        $this->assertSame($technician->id, $request->completed_by);
        $this->assertSame('completion', $request->attachments->first()->kind);
        $this->assertSame('completed', $request->actions->last()->action);
    }

    public function test_a_request_that_is_not_approved_cannot_be_assigned(): void
    {
        $request = $this->submitRequest();
        $this->manager->givePermissionTo('Assign Requests');

        $this->actingAs($this->manager)->post(route('requests.assign', $request), ['assigned_to' => $this->manager->id])->assertForbidden();
    }

    public function test_attachments_are_stored_privately_and_only_viewers_can_download(): void
    {
        Storage::fake('local');

        $request = $this->submitRequest(['attachments' => [UploadedFile::fake()->create('quotation.pdf', 200, 'application/pdf')]]);

        $attachment = $request->attachments->first();
        $this->assertSame('quotation.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);

        $url = route('requests.attachments.download', [$request, $attachment]);
        $this->actingAs($this->schoolHead)->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_an_attachment_of_the_wrong_type_or_size_is_refused(): void
    {
        Storage::fake('local');

        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form(['attachments' => [UploadedFile::fake()->create('script.exe', 10)]]))
            ->assertSessionHasErrors('attachments.0');

        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form(['attachments' => [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]]))
            ->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, SchoolRequest::count());
    }

    public function test_the_requester_can_remove_a_file_from_a_draft_only(): void
    {
        Storage::fake('local');

        $request = $this->submitRequest(['action' => 'draft', 'attachments' => [UploadedFile::fake()->create('quotation.pdf', 50, 'application/pdf')]]);
        $attachment = $request->attachments->first();
        $url = route('requests.attachments.destroy', [$request, $attachment]);

        $this->actingAs($this->schoolHead)->delete($url)->assertForbidden();
        $this->actingAs($this->requester)->delete($url)->assertSessionHasNoErrors();

        $this->assertSame(0, $request->attachments()->count());
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_the_dashboard_and_menu_count_what_awaits_the_user(): void
    {
        $this->submitRequest();

        $response = $this->actingAs($this->schoolHead)->get(route('home'));

        $response->assertOk();
        $this->assertSame(1, collect($response->viewData('tiles'))->firstWhere('label', 'Waiting for my signature')['value']);
    }

    public function test_a_department_or_category_with_requests_cannot_be_deleted(): void
    {
        $this->submitRequest();
        $admin = User::factory()->create();
        $admin->givePermissionTo(['Manage Departments', 'Manage Request Categories']);

        $this->actingAs($admin)->delete(route('departments.destroy', $this->department));
        $this->actingAs($admin)->delete(route('request-categories.destroy', $this->category));

        $this->assertNotNull($this->department->fresh());
        $this->assertNotNull($this->category->fresh());
    }
}
