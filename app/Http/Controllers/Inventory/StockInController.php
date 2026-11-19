<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockDocument;
use App\Models\Inventory\Store;
use App\Models\Inventory\Supplier;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class StockInController extends Controller
{
    public function __construct(private StockLedger $ledger) {}

    public function index(Request $request): View
    {
        $documents = StockDocument::with(['store', 'supplier', 'lines'])
            ->when($request->filled('store'), fn ($query) => $query->where('store_id', $request->integer('store')))
            ->latest('document_date')->latest('id')
            ->paginate(25)->withQueryString();

        $stores = Store::orderBy('name')->get();

        return view('inventory.stock-in.index', compact('documents', 'stores'));
    }

    public function create(): View
    {
        return view('inventory.stock-in.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $allowed = $this->storesFor($request)->pluck('id')->all();

        $data = $request->validate([
            'type' => ['required', Rule::in([StockDocument::TYPE_RECEIPT, StockDocument::TYPE_OPENING])],
            'store_id' => ['required', Rule::in($allowed)],
            'supplier_id' => ['nullable', Rule::exists('inv_suppliers', 'id')->where('is_active', true), 'required_if:type,'.StockDocument::TYPE_RECEIPT],
            'reference' => 'nullable|string|max:100',
            'document_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['required', 'distinct', Rule::exists('inv_items', 'id')->where('is_active', true)],
            'items.*.quantity' => 'required|numeric|gt:0|max:99999999',
            'items.*.unit_cost' => 'required|numeric|min:0|max:99999999',
        ], [
            'items.required' => 'Add at least one item.',
            'items.*.item_id.distinct' => 'An item can appear only once on a document.',
            'supplier_id.required_if' => 'Choose the supplier of this delivery.',
        ]);

        $document = DB::transaction(function () use ($data, $request) {
            $document = StockDocument::create([
                'number' => $this->ledger->nextNumber('SIN'),
                'type' => $data['type'],
                'store_id' => $data['store_id'],
                'supplier_id' => $data['type'] === StockDocument::TYPE_RECEIPT ? $data['supplier_id'] : null,
                'reference' => $data['reference'] ?? null,
                'document_date' => $data['document_date'],
                'notes' => $data['notes'] ?? null,
                'status' => StockDocument::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $line) {
                $document->lines()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ]);
            }

            return $document;
        });

        if ($request->input('action') === 'confirm') {
            return $this->confirmDocument($document, $request);
        }

        session()->flash('alert-success', "Saved as draft {$document->number}. Stock rises only when you confirm it.");

        return redirect()->route('inventory.stock-in.show', $document);
    }

    public function show(StockDocument $document): View
    {
        $document->load(['store', 'supplier', 'creator', 'confirmer', 'lines.item.unit']);

        return view('inventory.stock-in.show', compact('document'));
    }

    public function confirm(Request $request, StockDocument $document): RedirectResponse
    {
        abort_unless($this->canUseStore($request, $document->store_id), 403);

        return $this->confirmDocument($document, $request);
    }

    public function destroy(Request $request, StockDocument $document): RedirectResponse
    {
        abort_unless($this->canUseStore($request, $document->store_id), 403);

        if (! $document->isDraft()) {
            session()->flash('alert-danger', 'A confirmed document cannot be deleted. Correct it with a new entry.');

            return back();
        }

        $document->delete();

        session()->flash('alert-success', 'Draft deleted.');

        return redirect()->route('inventory.stock-in.index');
    }

    private function confirmDocument(StockDocument $document, Request $request): RedirectResponse
    {
        abort_unless($this->canUseStore($request, $document->store_id), 403);

        try {
            $this->ledger->confirm($document, $request->user());
        } catch (RuntimeException $e) {
            session()->flash('alert-danger', $e->getMessage());

            return redirect()->route('inventory.stock-in.show', $document);
        }

        session()->flash('alert-success', "{$document->number} confirmed. The stock has been added.");

        return redirect()->route('inventory.stock-in.show', $document);
    }

    /**
     * Stores the user may record stock for: their own stores, or all with set-up rights.
     */
    private function storesFor(Request $request)
    {
        $user = $request->user();

        return Store::where('is_active', true)
            ->when(! $user->can('Manage Inventory Setup'), fn ($query) => $query->whereHas('keepers', fn ($q) => $q->where('users.id', $user->id)))
            ->orderBy('name')
            ->get();
    }

    private function canUseStore(Request $request, int $storeId): bool
    {
        $user = $request->user();

        return $user->can('Manage Inventory Setup') || Store::whereKey($storeId)->whereHas('keepers', fn ($q) => $q->where('users.id', $user->id))->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $itemGroups = Item::with(['unit', 'category'])->where('is_active', true)->orderBy('name')->get()
            ->groupBy(fn ($item) => $item->category?->name ?? 'Other');

        return [
            'stores' => $this->storesFor(request()),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'itemGroups' => $itemGroups->sortKeys(),
        ];
    }
}
