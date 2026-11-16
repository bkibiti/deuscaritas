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
use Tests\TestCase;

/**
 * The request form and the approval chain, end to end through the HTTP routes.
 */
class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $departmentHead;

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

        [$this->departmentHead, $this->schoolHead, $this->coordinator, $this->manager, $this->director] = User::factory()->count(5)->create()->all();

        $this->school = School::create(['name' => 'Primary', 'head_user_id' => $this->schoolHead->id, 'is_active' => true]);
        $this->department = Department::create(['name' => 'Science', 'school_id' => $this->school->id, 'head_user_id' => $this->departmentHead->id, 'is_active' => true]);
        $this->category = RequestCategory::where('name', 'Maintenance')->first();

        $this->requester = User::factory()->create();
        $this->requester->department_id = $this->department->id;
        $this->requester->save();

        WorkflowStep::where('key', 'coordinator')->update(['user_id' => $this->coordinator->id]);
        WorkflowStep::where('key', 'manager')->update(['user_id' => $this->manager->id]);
        WorkflowStep::where('key', 'director')->update(['user_id' => $this->director->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'request_category_id' => $this->category->id,
            'title' => 'Classroom projector not working',
            'description' => 'The projector in Class 5B has stopped working.',
            'justification' => 'Examination preparation is tomorrow.',
            'required_date' => '2026-12-01',
            'quantity' => 1,
            'estimated_cost' => 250000,
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

    public function test_submitting_gives_a_number_and_starts_at_the_head_of_department(): void
    {
        $request = $this->submitRequest();

        $this->assertSame('REQ-'.now('Africa/Dar_es_Salaam')->year.'-000001', $request->number);
        $this->assertSame(SchoolRequest::STATUS_PENDING, $request->status);
        $this->assertSame('Awaiting Head of Department', $request->statusLabel());
        $this->assertSame($this->departmentHead->id, $request->currentApproval->approver_id);
        $this->assertSame(
            ['department_head', 'school_head', 'coordinator', 'manager'],
            $request->approvals->pluck('step_key')->all(),
            'The Director step is left out when neither the category nor the cost needs it.'
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

        foreach ([$this->departmentHead, $this->schoolHead, $this->coordinator] as $approver) {
            $this->approveAs($approver, $request);
            $this->assertSame(SchoolRequest::STATUS_PENDING, $request->fresh()->status);
        }

        $this->assertSame('Awaiting Manager', $request->fresh()->statusLabel());

        $this->approveAs($this->manager, $request);

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

        $this->approveAs($this->departmentHead, $request);

        $approval = $request->approvals()->where('step_key', 'department_head')->first();
        $this->assertSame($this->departmentHead->id, $approval->acted_by);
        $this->assertNotNull($approval->acted_at);
        $this->assertSame('OK', $approval->comment);
        $this->assertSame($request->fresh()->contentHash(), $approval->content_hash);
    }

    public function test_the_director_signs_when_the_category_always_requires_it(): void
    {
        $this->category->update(['requires_director' => true]);

        $request = $this->submitRequest();

        $this->assertSame('director', $request->approvals->last()->step_key);
        $this->assertSame($this->director->id, $request->approvals->last()->approver_id);

        foreach ([$this->departmentHead, $this->schoolHead, $this->coordinator, $this->manager] as $approver) {
            $this->approveAs($approver, $request);
        }
        $this->assertSame('Awaiting Director', $request->fresh()->statusLabel());

        $this->approveAs($this->director, $request);
        $this->assertSame(SchoolRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_the_director_signs_when_the_cost_reaches_the_category_threshold(): void
    {
        $this->category->update(['director_cost_threshold' => 200000]);

        $this->assertContains('director', $this->submitRequest(['estimated_cost' => 250000])->approvals->pluck('step_key'));
        $this->assertNotContains('director', $this->submitRequest(['estimated_cost' => 150000])->approvals->pluck('step_key'));
    }

    public function test_approving_needs_the_approvers_password(): void
    {
        $request = $this->submitRequest();

        $response = $this->actingAs($this->departmentHead)->post(route('requests.approve', $request), ['password' => 'wrong']);

        $response->assertSessionHasErrorsIn('approve', ['password']);
        $this->assertSame($this->departmentHead->id, $request->fresh()->currentApproval->approver_id);
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

        $this->actingAs($this->departmentHead)
            ->post(route('requests.reject', $request), ['password' => 'password'])
            ->assertSessionHasErrorsIn('reject', ['comment']);

        $this->actingAs($this->departmentHead)
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
        $this->approveAs($this->departmentHead, $request);

        $this->actingAs($this->schoolHead)
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
        $this->assertSame($this->departmentHead->id, $request->currentApproval->approver_id, 'A corrected request starts the chain again.');
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
        $this->departmentHead->department_id = $this->department->id;
        $this->departmentHead->save();

        $request = $this->submitRequest([], $this->departmentHead);

        $first = $request->approvals->first();
        $this->assertSame(RequestApproval::STATUS_SKIPPED, $first->status);
        $this->assertSame($this->schoolHead->id, $request->currentApproval->approver_id);
    }

    public function test_a_department_for_all_schools_still_goes_to_the_head_of_the_chosen_school(): void
    {
        $ict = Department::create(['name' => 'ICT', 'school_id' => null, 'head_user_id' => $this->departmentHead->id, 'is_active' => true]);
        $this->requester->department_id = $ict->id;
        $this->requester->save();

        $this->actingAs($this->requester)
            ->post(route('requests.store'), $this->form())
            ->assertSessionHasErrors('school_id');

        $request = $this->submitRequest(['school_id' => $this->school->id]);

        $this->assertSame($this->school->id, $request->school_id);
        $schoolStep = $request->approvals->firstWhere('step_key', 'school_head');
        $this->assertNotNull($schoolStep, 'The Head of School step is not skipped for an all-schools department.');
        $this->assertSame($this->schoolHead->id, $schoolStep->approver_id);
    }

    public function test_a_request_stays_a_draft_when_a_step_has_nobody_to_sign_it(): void
    {
        WorkflowStep::where('key', 'manager')->update(['user_id' => null]);

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

        $this->assertSame(['department_head', 'school_head', 'manager'], $this->submitRequest()->approvals->pluck('step_key')->all());
    }

    public function test_a_user_without_a_department_cannot_raise_a_request(): void
    {
        $nobody = User::factory()->create();

        $this->actingAs($nobody)->get(route('requests.create'))->assertOk()->assertSee('not linked to a department');
        $this->actingAs($nobody)->post(route('requests.store'), $this->form())->assertSessionHasErrors('department');
        $this->assertSame(0, SchoolRequest::count());
    }

    public function test_who_can_see_a_request(): void
    {
        $request = $this->submitRequest();
        $outsider = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('View All Requests');

        $this->actingAs($this->requester)->get(route('requests.show', $request))->assertOk();
        $this->actingAs($this->departmentHead)->get(route('requests.show', $request))->assertOk()->assertSee('Approve and sign');
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
        $this->actingAs($this->departmentHead)->get(route('requests.index', ['scope' => 'inbox']))->assertOk()->assertSee($request->number);
        $this->actingAs($this->schoolHead)->get(route('requests.index', ['scope' => 'inbox']))->assertOk()->assertDontSee($request->number);
        $this->actingAs($this->schoolHead)->get(route('requests.index', ['scope' => 'all']))->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('View All Requests');
        $this->actingAs($viewer)->get(route('requests.index', ['scope' => 'all']))->assertOk()->assertSee($request->number)->assertDontSee('Still a draft');
    }

    public function test_the_requester_can_cancel_until_a_decision_is_made(): void
    {
        $request = $this->submitRequest();

        $this->actingAs($this->departmentHead)->post(route('requests.cancel', $request))->assertForbidden();
        $this->actingAs($this->requester)->post(route('requests.cancel', $request))->assertSessionHasNoErrors();
        $this->assertSame(SchoolRequest::STATUS_CANCELLED, $request->fresh()->status);

        $this->actingAs($this->departmentHead)->post(route('requests.approve', $request), ['password' => 'password'])->assertForbidden();
    }

    public function test_an_approved_request_is_assigned_then_completed_with_evidence(): void
    {
        Storage::fake('local');

        $request = $this->submitRequest();
        foreach ([$this->departmentHead, $this->schoolHead, $this->coordinator, $this->manager] as $approver) {
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
        $this->actingAs($this->departmentHead)->get($url)->assertOk();
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

        $this->actingAs($this->departmentHead)->delete($url)->assertForbidden();
        $this->actingAs($this->requester)->delete($url)->assertSessionHasNoErrors();

        $this->assertSame(0, $request->attachments()->count());
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_the_dashboard_and_menu_count_what_awaits_the_user(): void
    {
        $this->submitRequest();

        $response = $this->actingAs($this->departmentHead)->get(route('home'));

        $response->assertOk();
        $this->assertSame(1, collect($response->viewData('mine'))->firstWhere('label', 'Awaiting my action')['value']);
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
