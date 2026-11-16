<?php

namespace App\Http\Controllers;

use App\Models\RequestAttachment;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * After approval: hand the request to someone to carry out, then mark it completed.
 */
class RequestFulfilmentController extends Controller
{
    public function assign(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        abort_unless($schoolRequest->canBeAssignedBy($request->user()), 403);

        $data = $request->validateWithBag('assign', [
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 1)],
            'note' => 'nullable|string|max:1000',
        ]);

        $workflow->assign($schoolRequest, $request->user(), User::findOrFail($data['assigned_to']), $data['note'] ?? null);

        session()->flash('alert-success', 'Request '.$schoolRequest->number.' assigned.');

        return redirect()->route('requests.show', $schoolRequest);
    }

    public function complete(Request $request, SchoolRequest $schoolRequest, RequestWorkflow $workflow): RedirectResponse
    {
        abort_unless($schoolRequest->canBeCompletedBy($request->user()), 403);

        $data = $request->validateWithBag('complete', [
            'note' => 'nullable|string|max:1000',
            'evidence' => 'nullable|array|max:10',
            'evidence.*' => RequestAttachment::FILE_RULE,
        ], [
            'evidence.*.max' => 'Each file must be 5 MB or smaller.',
            'evidence.*.mimes' => 'Files must be PDF, image, Word or Excel files.',
        ]);

        $workflow->complete($schoolRequest, $request->user(), $data['note'] ?? null);

        foreach ($request->file('evidence', []) as $file) {
            RequestAttachment::create([
                'request_id' => $schoolRequest->id,
                'uploaded_by' => $request->user()->id,
                'kind' => RequestAttachment::KIND_COMPLETION,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $file->store('requests/'.$schoolRequest->id, 'local'),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        session()->flash('alert-success', 'Request '.$schoolRequest->number.' completed.');

        return redirect()->route('requests.show', $schoolRequest);
    }
}
