<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Store;
use App\Models\Inventory\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['store' => 'nullable|integer']);

        $stores = Store::orderBy('name')->get(['id', 'name', 'is_active']);
        $storeId = $request->query('store');

        $items = Item::with(['store', 'category', 'unit'])
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->orderBy('name')
            ->get();

        return view('inventory.items.index', [
            'items' => $items,
            'stores' => $stores,
            'storeId' => $storeId,
            'categories' => ItemCategory::orderBy('name')->get(['id', 'store_id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Item::create($this->validated($request));

        session()->flash('alert-success', 'Item added.');

        return back();
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $item->update($this->validated($request, $item));

        session()->flash('alert-success', 'Item updated.');

        return back();
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->delete();

        session()->flash('alert-success', 'Item deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'store_id' => 'required|integer|exists:inv_stores,id',
            // A category is optional, but it must be one of the chosen store's.
            'item_category_id' => [
                'nullable', 'integer',
                Rule::exists('inv_item_categories', 'id')->where('store_id', $request->input('store_id')),
            ],
            'unit_id' => 'required|integer|exists:inv_units,id',
            'code' => ['nullable', 'string', 'max:30', Rule::unique('inv_items', 'code')->ignore($item?->id)],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('inv_items', 'name')->where('store_id', $request->input('store_id'))->ignore($item?->id),
            ],
            'reorder_level' => 'nullable|numeric|min:0',
        ], [
            'name.unique' => 'This store already has an item with that name.',
            'item_category_id.exists' => 'Choose a category that belongs to the selected store.',
        ]);

        $data['item_category_id'] = $data['item_category_id'] ?? null;
        $data['code'] = $data['code'] ?? null;
        $data['reorder_level'] = $data['reorder_level'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
