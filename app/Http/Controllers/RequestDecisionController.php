<?php

namespace App\Http\Controllers;

use App\Models\SchoolRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What the approver whose turn it is can do. Approving and rejecting are signatures, so both
 * ask for the approver's password again; returning a request for correction does not.
 */
class RequestDecisionController extends Controller
{
    public function approve(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        abort_unless($schoolRequest->isAwaiting($request->user()), 403);

        $data = $request->validateWithBag('approve', [
            'comment' => 'nullable|string|max:1000',
            'password' => 'required|current_password',
        ], $this->passwordMessages());

        $workflow->approve($schoolRequest, $request->user(), $data['comment'] ?? null);

        session()->flash('alert-success', 'Request '.$schoolRequest->number.' approved and signed.');

        return redirect()->route('requests.show', $schoolRequest);
    }

    public function reject(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        abort_unless($schoolRequest->isAwaiting($request->user()), 403);

        $data = $request->validateWithBag('reject', [
            'comment' => 'required|string|max:1000',
            'password' => 'required|current_password',
        ], ['comment.required' => 'Give the reason for rejecting this request.'] + $this->passwordMessages());

        $workflow->reject($schoolRequest, $request->user(), $data['comment']);

        session()->flash('alert-success', 'Request '.$schoolRequest->number.' rejected.');

        return redirect()->route('requests.show', $schoolRequest);
    }

    public function returnForCorrection(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        abort_unless($schoolRequest->isAwaiting($request->user()), 403);

        $data = $request->validateWithBag('return', [
            'comment' => 'required|string|max:1000',
        ], ['comment.required' => 'Say what needs to be corrected.']);

        $workflow->returnForCorrection($schoolRequest, $request->user(), $data['comment']);

        session()->flash('alert-success', 'Request '.$schoolRequest->number.' returned for correction.');

        return redirect()->route('requests.show', $schoolRequest);
    }

    /**
     * @return array<string, string>
     */
    private function passwordMessages(): array
    {
        return [
            'password.required' => 'Enter your password to sign.',
            'password.current_password' => 'That password is not correct, so the request was not signed.',
        ];
    }
}
