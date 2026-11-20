<?php

namespace App\Services;

use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockDocument;
use App\Models\Inventory\StockLedgerEntry;
use App\Models\Inventory\Store;
use App\Models\RequestItem;
use App\Models\SchoolRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place stock quantities change. Every change is a ledger line written in the same
 * transaction as the balance update.
 */
class StockLedger
{
    /**
     * Next number for a stock document, e.g. SIN-2026-000001.
     */
    public function nextNumber(string $prefix): string
    {
        $year = (int) now('Africa/Dar_es_Salaam')->format('Y');

        DB::table('inv_document_sequences')->insertOrIgnore(['prefix' => $prefix, 'year' => $year, 'last_number' => 0]);

        $where = ['prefix' => $prefix, 'year' => $year];
        $next = (int) DB::table('inv_document_sequences')->where($where)->lockForUpdate()->value('last_number') + 1;
        DB::table('inv_document_sequences')->where($where)->update(['last_number' => $next]);

        return sprintf('%s-%d-%06d', $prefix, $year, $next);
    }

    /**
     * Confirm a draft Stock In document: write its ledger lines and raise the balances.
     */
    public function confirm(StockDocument $document, User $actor): StockDocument
    {
        return DB::transaction(function () use ($document, $actor) {
            $document = StockDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! $document->isDraft()) {
                throw new RuntimeException('This document is already confirmed.');
            }

            $lines = $document->lines()->get();

            if ($lines->isEmpty()) {
                throw new RuntimeException('Add at least one item before confirming.');
            }

            foreach ($lines as $line) {
                $this->move($document->store_id, $line->item_id, (float) $line->quantity, (float) $line->unit_cost, $document->id, $document->document_date);
            }

            $document->forceFill([
                'status' => StockDocument::STATUS_CONFIRMED,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ])->save();

            return $document;
        });
    }

    /**
     * Issue stock for an approved request that is in progress. The lines say, for each request
     * line, which store gives how much. One issue document is written per store, the balances
     * fall at their average cost, and the request is completed. Nothing is issued when any line
     * is more than was asked for, more than the store holds, or the request was already issued.
     *
     * @param  array<int, array{request_item_id: int, store_id: int, quantity: float}>  $lines
     * @return Collection<int, StockDocument> the issue documents
     */
    public function issue(SchoolRequest $request, User $actor, User $receiver, array $lines, RequestWorkflow $workflow, ?string $note = null): Collection
    {
        return DB::transaction(function () use ($request, $actor, $receiver, $lines, $workflow, $note) {
            $request = SchoolRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== SchoolRequest::STATUS_IN_PROGRESS) {
                throw new RuntimeException('This request has already been issued or is not in progress.');
            }

            $requestLines = RequestItem::where('request_id', $request->id)->lockForUpdate()->get()->keyBy('id');
            $byStore = [];

            foreach ($lines as $line) {
                $requestLine = $requestLines[$line['request_item_id']] ?? null;
                $quantity = round((float) $line['quantity'], 3);

                if (! $requestLine) {
                    throw new RuntimeException('A line does not belong to this request.');
                }

                if ($quantity <= 0) {
                    continue;
                }

                if ($quantity > round((float) $requestLine->quantity - (float) $requestLine->issued_quantity, 3)) {
                    throw new RuntimeException($requestLine->item->name.': more than the request asked for.');
                }

                $byStore[$line['store_id']][] = ['line' => $requestLine, 'quantity' => $quantity];
            }

            if ($byStore === []) {
                throw new RuntimeException('Enter a quantity to issue on at least one line.');
            }

            $documents = collect();
            $today = now('Africa/Dar_es_Salaam')->toDateString();

            foreach ($byStore as $storeId => $storeLines) {
                $document = StockDocument::create([
                    'number' => $this->nextNumber('SOUT'),
                    'type' => StockDocument::TYPE_ISSUE,
                    'store_id' => $storeId,
                    'request_id' => $request->id,
                    'received_by' => $receiver->id,
                    'document_date' => $today,
                    'notes' => $note,
                    'status' => StockDocument::STATUS_CONFIRMED,
                    'created_by' => $actor->id,
                ]);
                $document->forceFill(['confirmed_by' => $actor->id, 'confirmed_at' => now()])->save();

                foreach ($storeLines as ['line' => $requestLine, 'quantity' => $quantity]) {
                    $cost = $this->issueOut($storeId, $requestLine->item_id, $quantity, $document->id, $today);

                    $document->lines()->create(['item_id' => $requestLine->item_id, 'quantity' => $quantity, 'unit_cost' => $cost]);
                    $requestLine->update(['issued_quantity' => round((float) $requestLine->issued_quantity + $quantity, 3)]);
                }

                $documents->push($document);
            }

            $workflow->complete($request, $actor, trim('Items issued to '.$receiver->name.' ('.$documents->pluck('number')->implode(', ').'). '.$note));

            return $documents;
        });
    }

    /**
     * For each line of a request, the active stores that hold the item and how much, the store
     * holding the most first. These are the stores an item can be issued from.
     *
     * @return array<int, Collection<int, array{id: int, name: string, quantity: float}>>
     */
    public function storesHolding(SchoolRequest $request): array
    {
        $stores = Store::where('is_active', true)->get()->keyBy('id');
        $options = [];

        foreach ($request->items as $line) {
            $options[$line->id] = StockBalance::where('item_id', $line->item_id)->where('quantity', '>', 0)->get()
                ->filter(fn ($balance) => $stores->has($balance->store_id))
                ->map(fn ($balance) => ['id' => (int) $balance->store_id, 'name' => $stores[$balance->store_id]->name, 'quantity' => (float) $balance->quantity])
                ->sortByDesc('quantity')->values();
        }

        return $options;
    }

    /**
     * Take stock out of a store at its average cost. Returns the unit cost used.
     */
    private function issueOut(int $storeId, int $itemId, float $quantity, int $documentId, string $date): float
    {
        $balance = StockBalance::where(['store_id' => $storeId, 'item_id' => $itemId])->lockForUpdate()->first();
        $item = Item::find($itemId);

        if (! $balance || (float) $balance->quantity < $quantity) {
            $held = $balance ? rtrim(rtrim(number_format((float) $balance->quantity, 3), '0'), '.') : '0';

            throw new RuntimeException($item->name.': the store holds only '.$held.'.');
        }

        $cost = round($balance->averageCost(), 2);
        $this->move($storeId, $itemId, -$quantity, $cost, $documentId, $date);

        return $cost;
    }

    private function move(int $storeId, int $itemId, float $quantity, float $unitCost, int $documentId, $date): void
    {
        DB::table('inv_stock_balances')->insertOrIgnore(['store_id' => $storeId, 'item_id' => $itemId, 'quantity' => 0, 'value' => 0]);

        $balance = StockBalance::where(['store_id' => $storeId, 'item_id' => $itemId])->lockForUpdate()->first();

        $newQuantity = round((float) $balance->quantity + $quantity, 3);
        $value = round($quantity * $unitCost, 2);

        // Taking out the last of an item clears its value exactly, so rounding leaves nothing behind.
        if ($quantity < 0 && $newQuantity === 0.0) {
            $value = -round((float) $balance->value, 2);
        }

        if ($newQuantity < 0) {
            throw new RuntimeException('Stock cannot go below zero.');
        }

        StockBalance::where(['store_id' => $storeId, 'item_id' => $itemId])->update([
            'quantity' => $newQuantity,
            'value' => round((float) $balance->value + $value, 2),
            'updated_at' => now(),
        ]);

        StockLedgerEntry::create([
            'store_id' => $storeId,
            'item_id' => $itemId,
            'document_id' => $documentId,
            'movement_date' => $date,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'value' => $value,
            'balance_after' => $newQuantity,
        ]);
    }
}
