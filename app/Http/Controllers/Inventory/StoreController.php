<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(): View
    {
        $stores = Store::with('keepers')->withCount('items')->orderBy('name')->get();
        $users = User::where('status', 1)->orderBy('name')->get(['id', 'name']);

        return view('inventory.stores.index', compact('stores', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $store = Store::create($data['store']);
        $store->keepers()->sync($data['keepers']);

        session()->flash('alert-success', 'Store added.');

        return back();
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $data = $this->validated($request, $store);

        $store->update($data['store']);
        $store->keepers()->sync($data['keepers']);

        session()->flash('alert-success', 'Store updated.');

        return back();
    }

    public function destroy(Store $store): RedirectResponse
    {
        if ($store->isInUse()) {
            session()->flash('alert-danger', 'This store has items or categories and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $store->delete();

        session()->flash('alert-success', 'Store deleted.');

        return back();
    }

    /**
     * @return array{store: array<string, mixed>, keepers: array<int, int>}
     */
    private function validated(Request $request, ?Store $store = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inv_stores', 'name')->ignore($store?->id)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('inv_stores', 'code')->ignore($store?->id)],
            'description' => 'nullable|string|max:255',
            'keepers' => 'nullable|array',
            'keepers.*' => ['integer', Rule::exists('users', 'id')->where('status', 1)],
        ]);

        return [
            'store' => [
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
            'keepers' => array_map('intval', $data['keepers'] ?? []),
        ];
    }
}
