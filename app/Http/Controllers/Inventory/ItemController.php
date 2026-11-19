<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['category' => 'nullable|integer']);

        $categoryId = $request->query('category');

        $items = Item::with(['category', 'unit'])
            ->when($categoryId, fn ($query) => $query->where('item_category_id', $categoryId))
            ->orderBy('name')
            ->get();

        return view('inventory.items.index', [
            'items' => $items,
            'categoryId' => $categoryId,
            'categories' => ItemCategory::orderBy('name')->get(['id', 'name']),
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
        if ($item->isInUse()) {
            session()->flash('alert-danger', 'This item is on a request and cannot be deleted. Deactivate it instead.');

            return back();
        }

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
            'item_category_id' => 'nullable|integer|exists:inv_item_categories,id',
            'unit_id' => 'required|integer|exists:inv_units,id',
            'code' => ['nullable', 'string', 'max:30', Rule::unique('inv_items', 'code')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:150', Rule::unique('inv_items', 'name')->ignore($item?->id)],
            'reorder_level' => 'nullable|numeric|min:0',
        ], [
            'name.unique' => 'There is already an item with that name.',
        ]);

        $data['item_category_id'] = $data['item_category_id'] ?? null;
        $data['code'] = $data['code'] ?? null;
        $data['reorder_level'] = $data['reorder_level'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
