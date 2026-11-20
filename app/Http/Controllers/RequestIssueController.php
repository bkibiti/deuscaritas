<?php

namespace App\Http\Controllers;

use App\Models\Inventory\StockDocument;
use App\Models\SchoolRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * The staff member a request was assigned to releases its items from the stores and says who
 * received them. This is the step that takes the stock out. Each store that gave items gets
 * an issue note that can be printed for the receiver to sign.
 */
class RequestIssueController extends Controller
{
    public function create(Request $request, SchoolRequest $schoolRequest, StockLedger $ledger): View
    {
        abort_unless($schoolRequest->canBeIssuedBy($request->user()), 403);

        $schoolRequest->load(['requester', 'department', 'school', 'items.item.unit']);

        return view('requests.issue', [
            'schoolRequest' => $schoolRequest,
            'stores' => $ledger->storesHolding($schoolRequest),
            'receivers' => User::where('status', 1)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, SchoolRequest $schoolRequest, StockLedger $ledger, RequestWorkflow $workflow): RedirectResponse
    {
        abort_unless($schoolRequest->canBeIssuedBy($request->user()), 403);

        $back = fn (string $message) => redirect()->route('requests.issue.create', $schoolRequest)->withInput()->withErrors(['issue' => $message], 'issue');

        $data = $request->validateWithBag('issue', [
            'received_by' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 1)],
            'note' => 'nullable|string|max:500',
            'confirm' => 'accepted',
            'lines' => 'required|array',
            'lines.*.store_id' => ['nullable', 'integer', Rule::exists('inv_stores', 'id')->where('is_active', true)],
            'lines.*.quantity' => 'nullable|numeric|min:0',
        ], [
            'received_by.required' => 'Choose who received the items.',
            'confirm.accepted' => 'Tick the box to confirm that the stock is taken out now.',
            'lines.*.quantity.min' => 'A quantity cannot be negative.',
        ]);

        $lines = [];
        foreach ($data['lines'] as $requestItemId => $line) {
            $quantity = (float) ($line['quantity'] ?? 0);

            if ($quantity > 0 && empty($line['store_id'])) {
                return $back('Choose the store each item comes from.');
            }

            $lines[] = ['request_item_id' => (int) $requestItemId, 'store_id' => (int) ($line['store_id'] ?? 0), 'quantity' => $quantity];
        }

        try {
            $documents = $ledger->issue($schoolRequest, $request->user(), User::findOrFail($data['received_by']), $lines, $workflow, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return $back($e->getMessage());
        }

        session()->flash('alert-success', 'Items issued ('.$documents->pluck('number')->implode(', ').'). Request '.$schoolRequest->number.' completed. You can print the issue note below.');

        return redirect()->route('requests.show', $schoolRequest);
    }

    /**
     * The printable issue note for one store's issue: what was asked, what was given, and
     * signature lines for the person who issued and the person who received.
     */
    public function note(Request $request, SchoolRequest $schoolRequest, StockDocument $document): View
    {
        abort_unless($schoolRequest->canBeViewedBy($request->user()), 403);
        abort_unless($document->request_id === $schoolRequest->id && $document->isIssue(), 404);

        $document->load(['store', 'creator', 'receiver', 'lines.item.unit']);
        $schoolRequest->load(['requester', 'department', 'school', 'items']);

        $asked = $schoolRequest->items->keyBy('item_id');
        $short = $document->lines->filter(fn ($line) => (float) $line->quantity < (float) ($asked[$line->item_id]->quantity ?? 0));

        return view('requests.issue_note', compact('schoolRequest', 'document', 'asked', 'short'));
    }
}
