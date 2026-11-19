<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockLedgerEntry;
use App\Models\Inventory\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockBalanceController extends Controller
{
    /**
     * What each store holds now, with quantity, average cost and value, and a low-stock flag.
     */
    public function index(Request $request): View
    {
        $stores = Store::orderBy('name')->get();
        $storeId = $request->integer('store') ?: $stores->first()?->id;

        $balances = StockBalance::with(['item.unit', 'item.category'])
            ->where('store_id', $storeId)
            ->get()
            ->sortBy(fn ($balance) => $balance->item->name)
            ->values();

        $totalValue = (float) $balances->sum('value');

        return view('inventory.stock.index', compact('stores', 'storeId', 'balances', 'totalValue'));
    }

    /**
     * The stock card: every movement of one item in one store, oldest first.
     */
    public function card(Store $store, Item $item): View
    {
        $entries = StockLedgerEntry::with('document')
            ->where(['store_id' => $store->id, 'item_id' => $item->id])
            ->orderBy('id')
            ->get();

        $item->load('unit');

        return view('inventory.stock.card', compact('store', 'item', 'entries'));
    }
}
