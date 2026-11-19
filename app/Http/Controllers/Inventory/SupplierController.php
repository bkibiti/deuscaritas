<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::orderBy('name')->get();

        return view('inventory.suppliers.index', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        session()->flash('alert-success', 'Supplier added.');

        return back();
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        session()->flash('alert-success', 'Supplier updated.');

        return back();
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->isInUse()) {
            session()->flash('alert-danger', 'This supplier has deliveries recorded and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $supplier->delete();

        session()->flash('alert-success', 'Supplier deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('inv_suppliers', 'name')->ignore($supplier?->id)],
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:30',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
