<?php

namespace App\Http\Controllers;

use App\Models\RequestCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequestCategoryController extends Controller
{
    public function index(): View
    {
        $categories = RequestCategory::orderBy('name')->get();

        return view('masters.request_categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        RequestCategory::create($this->validated($request));

        session()->flash('alert-success', 'Request category added.');

        return back();
    }

    public function update(Request $request, RequestCategory $requestCategory): RedirectResponse
    {
        $requestCategory->update($this->validated($request, $requestCategory));

        session()->flash('alert-success', 'Request category updated.');

        return back();
    }

    public function destroy(RequestCategory $requestCategory): RedirectResponse
    {
        if ($requestCategory->isInUse()) {
            session()->flash('alert-danger', 'This category is used on requests and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $requestCategory->delete();

        session()->flash('alert-success', 'Request category deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?RequestCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('request_categories', 'name')->ignore($category?->id)],
            'director_cost_threshold' => 'nullable|numeric|min:0',
        ]);

        $data['director_cost_threshold'] = $data['director_cost_threshold'] ?? null;
        $data['requires_director'] = $request->boolean('requires_director');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
