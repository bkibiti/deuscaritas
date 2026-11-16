<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ItemCategory::with('store')->withCount('items')->orderBy('store_id')->orderBy('name')->get();
        $stores = Store::orderBy('name')->get(['id', 'name', 'is_active']);

        return view('inventory.categories.index', compact('categories', 'stores'));
    }

    public function store(Request $request): RedirectResponse
    {
        ItemCategory::create($this->validated($request));

        session()->flash('alert-success', 'Category added.');

        return back();
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $data = $this->validated($request, $itemCategory);

        // Its items live in one store, so a category that has items stays in its store.
        if ($itemCategory->isInUse() && (int) $data['store_id'] !== (int) $itemCategory->store_id) {
            return back()->withErrors(['store_id' => 'This category has items, so it cannot move to another store.'])->withInput();
        }

        $itemCategory->update($data);

        session()->flash('alert-success', 'Category updated.');

        return back();
    }

    public function destroy(ItemCategory $itemCategory): RedirectResponse
    {
        if ($itemCategory->isInUse()) {
            session()->flash('alert-danger', 'This category has items and cannot be deleted.');

            return back();
        }

        $itemCategory->delete();

        session()->flash('alert-success', 'Category deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ItemCategory $category = null): array
    {
        return $request->validate([
            'store_id' => 'required|integer|exists:inv_stores,id',
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('inv_item_categories', 'name')->where('store_id', $request->input('store_id'))->ignore($category?->id),
            ],
        ], [
            'name.unique' => 'This store already has a category with that name.',
        ]);
    }
}
