<?php

namespace App\Http\Controllers;

use App\Models\RequestAttachment;
use App\Models\SchoolRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestAttachmentController extends Controller
{
    /**
     * Files live on the private disk: only someone who may see the request can download them.
     */
    public function download(Request $request, SchoolRequest $schoolRequest, RequestAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->request_id === $schoolRequest->id, 404);
        abort_unless($schoolRequest->canBeViewedBy($request->user()), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    /**
     * The requester can remove a supporting file while the request is still editable.
     */
    public function destroy(Request $request, SchoolRequest $schoolRequest, RequestAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->request_id === $schoolRequest->id, 404);
        abort_unless($schoolRequest->canBeEditedBy($request->user()), 403);
        abort_unless($attachment->kind === RequestAttachment::KIND_SUPPORTING, 403);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        session()->flash('alert-success', 'Attachment removed.');

        return back();
    }
}
