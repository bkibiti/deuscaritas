<?php

namespace App\Services;

use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockDocument;
use App\Models\Inventory\StockLedgerEntry;
use App\Models\User;
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

    private function move(int $storeId, int $itemId, float $quantity, float $unitCost, int $documentId, $date): void
    {
        DB::table('inv_stock_balances')->insertOrIgnore(['store_id' => $storeId, 'item_id' => $itemId, 'quantity' => 0, 'value' => 0]);

        $balance = StockBalance::where(['store_id' => $storeId, 'item_id' => $itemId])->lockForUpdate()->first();

        $newQuantity = round((float) $balance->quantity + $quantity, 3);
        $value = round($quantity * $unitCost, 2);

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
