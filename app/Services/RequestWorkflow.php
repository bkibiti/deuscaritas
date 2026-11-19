<?php

namespace App\Services;

use App\Models\RequestAction;
use App\Models\RequestApproval;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a request through the approval chain. Every change of state happens here, inside a
 * transaction, and leaves a line on the request's timeline.
 */
class RequestWorkflow
{
    /**
     * Send a draft (or a returned request) into the approval chain.
     */
    public function submit(SchoolRequest $request, User $actor): SchoolRequest
    {
        if (! $request->canBeEditedBy($actor)) {
            throw ValidationException::withMessages(['status' => 'Only a draft or a returned request can be submitted, by the person who raised it.']);
        }

        $request->loadMissing(['department', 'school', 'category', 'requester.roles']);

        if ($request->school_id === null) {
            throw ValidationException::withMessages(['school_id' => 'Choose the school this request is for.']);
        }

        $steps = $this->stepsFor($request);

        return DB::transaction(function () use ($request, $actor, $steps) {
            $resubmission = $request->status === SchoolRequest::STATUS_RETURNED;

            if ($request->number === null) {
                $request->number = $this->nextNumber();
            }

            $round = (int) $request->approvals()->max('round') + 1;
            $now = now();
            $pendingSet = false;

            foreach ($steps as [$step, $schoolId]) {
                $status = RequestApproval::STATUS_WAITING;
                $comment = null;

                if ($request->requester->holdsRole($step->role_id, $schoolId)) {
                    // Nobody signs their own request: the step is passed and recorded as such.
                    // (Another holder of the role is not asked: the requester already holds it.)
                    $status = RequestApproval::STATUS_SKIPPED;
                    $comment = 'The requester holds this step.';
                } elseif (! $pendingSet) {
                    $status = RequestApproval::STATUS_PENDING;
                    $pendingSet = true;
                }

                RequestApproval::create([
                    'request_id' => $request->id,
                    'round' => $round,
                    'position' => $step->position,
                    'step_key' => $step->key,
                    'step_name' => $step->name,
                    'role_id' => $step->role_id,
                    'scope_school_id' => $schoolId,
                    'status' => $status,
                    'activated_at' => $status === RequestApproval::STATUS_PENDING ? $now : null,
                    'acted_at' => $status === RequestApproval::STATUS_SKIPPED ? $now : null,
                    'comment' => $comment,
                ]);
            }

            $request->status = SchoolRequest::STATUS_PENDING;
            $request->submitted_at = $now;
            $request->decided_at = null;
            $request->save();

            $this->record($request, $actor, $resubmission ? 'resubmitted' : 'submitted');

            if (! $pendingSet) {
                $this->markApproved($request, $actor);
            }

            return $request->refresh();
        });
    }

    /**
     * The current approver signs: the request moves to the next step, or is approved.
     */
    public function approve(SchoolRequest $request, User $actor, ?string $comment = null): SchoolRequest
    {
        return DB::transaction(function () use ($request, $actor, $comment) {
            $approval = $this->currentApprovalFor($request, $actor);

            $approval->update([
                'status' => RequestApproval::STATUS_APPROVED,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $comment,
                'content_hash' => $request->contentHash(),
            ]);

            $this->record($request, $actor, 'approved', $approval->step_name, $comment);

            $next = RequestApproval::where('request_id', $request->id)
                ->where('round', $approval->round)
                ->where('status', RequestApproval::STATUS_WAITING)
                ->orderBy('position')
                ->first();

            if ($next) {
                $next->update(['status' => RequestApproval::STATUS_PENDING, 'activated_at' => now()]);
            } else {
                $this->markApproved($request, $actor);
            }

            return $request->refresh();
        });
    }

    /**
     * The current approver rejects: the request stops here.
     */
    public function reject(SchoolRequest $request, User $actor, string $reason): SchoolRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason) {
            $approval = $this->currentApprovalFor($request, $actor);

            $approval->update([
                'status' => RequestApproval::STATUS_REJECTED,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $reason,
                'content_hash' => $request->contentHash(),
            ]);

            $request->status = SchoolRequest::STATUS_REJECTED;
            $request->decided_at = now();
            $request->save();

            $this->record($request, $actor, 'rejected', $approval->step_name, $reason);

            return $request->refresh();
        });
    }

    /**
     * The current approver sends the request back to the requester to correct and send again.
     */
    public function returnForCorrection(SchoolRequest $request, User $actor, string $comment): SchoolRequest
    {
        return DB::transaction(function () use ($request, $actor, $comment) {
            $approval = $this->currentApprovalFor($request, $actor);

            $approval->update([
                'status' => RequestApproval::STATUS_RETURNED,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            $request->status = SchoolRequest::STATUS_RETURNED;
            $request->save();

            $this->record($request, $actor, 'returned', $approval->step_name, $comment);

            return $request->refresh();
        });
    }

    public function cancel(SchoolRequest $request, User $actor, ?string $reason = null): SchoolRequest
    {
        if (! $request->canBeCancelledBy($actor)) {
            throw ValidationException::withMessages(['status' => 'This request can no longer be cancelled.']);
        }

        return DB::transaction(function () use ($request, $actor, $reason) {
            $request->status = SchoolRequest::STATUS_CANCELLED;
            $request->cancelled_at = now();
            $request->save();

            $this->record($request, $actor, 'cancelled', null, $reason);

            return $request->refresh();
        });
    }

    /**
     * Hand an approved request to the person who will carry it out.
     */
    public function assign(SchoolRequest $request, User $actor, User $assignee, ?string $note = null): SchoolRequest
    {
        if (! in_array($request->status, [SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages(['status' => 'Only an approved request can be assigned.']);
        }

        return DB::transaction(function () use ($request, $actor, $assignee, $note) {
            $request->status = SchoolRequest::STATUS_IN_PROGRESS;
            $request->assigned_to = $assignee->id;
            $request->assigned_at = now();
            $request->save();

            $this->record($request, $actor, 'assigned', null, trim('Assigned to '.$assignee->name.'. '.$note));

            return $request->refresh();
        });
    }

    public function complete(SchoolRequest $request, User $actor, ?string $note = null): SchoolRequest
    {
        if ($request->status !== SchoolRequest::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['status' => 'Only a request in progress can be completed.']);
        }

        return DB::transaction(function () use ($request, $actor, $note) {
            $request->status = SchoolRequest::STATUS_COMPLETED;
            $request->completed_by = $actor->id;
            $request->completed_at = now();
            $request->save();

            $this->record($request, $actor, 'completed', null, $note);

            return $request->refresh();
        });
    }

    /**
     * The chain for this request: each step that applies, with the school its signer must
     * belong to (null for any). Refuses to start when nobody holds a step's role.
     *
     * @return array<int, array{0: WorkflowStep, 1: int|null}>
     */
    private function stepsFor(SchoolRequest $request): array
    {
        $steps = [];
        $missing = [];

        foreach (WorkflowStep::where('is_active', true)->orderBy('position')->get() as $step) {
            if (! $step->appliesTo($request)) {
                continue;
            }

            $schoolId = $step->schoolFor($request);

            // Someone must be able to sign: a holder of the role, or the requester (who then
            // passes the step).
            $covered = $step->role_id !== null
                && ($request->requester->holdsRole($step->role_id, $schoolId) || $step->holdersFor($request)->exists());

            if (! $covered) {
                $missing[] = $step->role_id === null
                    ? "a role for {$step->name}"
                    : "a holder of the {$step->role->name} role".($schoolId !== null ? " at {$request->school->name}" : '');

                continue;
            }

            $steps[] = [$step, $schoolId];
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'approvers' => 'This request cannot be submitted yet: there is no '.implode(', no ', $missing).'. Ask the system administrator to set it up.',
            ]);
        }

        return $steps;
    }

    private function currentApprovalFor(SchoolRequest $request, User $actor): RequestApproval
    {
        $approval = RequestApproval::where('request_id', $request->id)
            ->where('status', RequestApproval::STATUS_PENDING)
            ->lockForUpdate()
            ->first();

        if ($request->status !== SchoolRequest::STATUS_PENDING || ! $approval || ! $approval->isActionableBy($actor)) {
            throw ValidationException::withMessages(['status' => 'This request is not waiting for your action.']);
        }

        return $approval;
    }

    private function markApproved(SchoolRequest $request, User $actor): void
    {
        $request->status = SchoolRequest::STATUS_APPROVED;
        $request->decided_at = now();
        $request->save();

        $this->record($request, $actor, 'fully_approved');
    }

    /**
     * REQ-2026-000145: a counter per year, locked while it is read and increased so two
     * requests submitted at the same moment never share a number.
     */
    private function nextNumber(): string
    {
        $year = (int) now('Africa/Dar_es_Salaam')->format('Y');

        DB::table('request_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $last = (int) DB::table('request_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
        $next = $last + 1;

        DB::table('request_sequences')->where('year', $year)->update(['last_number' => $next]);

        return sprintf('REQ-%d-%06d', $year, $next);
    }

    private function record(SchoolRequest $request, User $actor, string $action, ?string $stepName = null, ?string $note = null): void
    {
        RequestAction::create([
            'request_id' => $request->id,
            'user_id' => $actor->id,
            'action' => $action,
            'step_name' => $stepName,
            'note' => $note === '' ? null : $note,
        ]);
    }
}
