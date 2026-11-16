<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $units = Unit::withCount('items')->orderBy('name')->get();

        return view('inventory.units.index', compact('units'));
    }

    public function store(Request $request): RedirectResponse
    {
        Unit::create($this->validated($request));

        session()->flash('alert-success', 'Unit added.');

        return back();
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($this->validated($request, $unit));

        session()->flash('alert-success', 'Unit updated.');

        return back();
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->isInUse()) {
            session()->flash('alert-danger', 'This unit is used by items and cannot be deleted.');

            return back();
        }

        $unit->delete();

        session()->flash('alert-success', 'Unit deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('inv_units', 'name')->ignore($unit?->id)],
            'abbreviation' => ['required', 'string', 'max:15', Rule::unique('inv_units', 'abbreviation')->ignore($unit?->id)],
        ]);
    }
}
