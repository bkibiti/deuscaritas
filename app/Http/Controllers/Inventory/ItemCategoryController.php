<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ItemCategory::withCount('items')->orderBy('name')->get();

        return view('inventory.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        ItemCategory::create($this->validated($request));

        session()->flash('alert-success', 'Category added.');

        return back();
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->update($this->validated($request, $itemCategory));

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
            'name' => ['required', 'string', 'max:100', Rule::unique('inv_item_categories', 'name')->ignore($category?->id)],
        ]);
    }
}
