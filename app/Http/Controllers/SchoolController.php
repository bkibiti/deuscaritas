<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(): View
    {
        $schools = School::withCount(['departments', 'users'])->orderBy('name')->get();

        return view('masters.schools.index', compact('schools'));
    }

    public function store(Request $request): RedirectResponse
    {
        School::create($this->validated($request));

        session()->flash('alert-success', 'School added.');

        return back();
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $school->update($this->validated($request, $school));

        session()->flash('alert-success', 'School updated.');

        return back();
    }

    public function destroy(School $school): RedirectResponse
    {
        if ($school->isInUse()) {
            session()->flash('alert-danger', 'This school has departments or requests and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $school->delete();

        session()->flash('alert-success', 'School deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?School $school = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('schools', 'name')->ignore($school?->id)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('schools', 'code')->ignore($school?->id)],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
