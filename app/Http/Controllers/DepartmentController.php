<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::with(['school', 'head'])->orderBy('name')->get();
        $schools = School::orderBy('name')->get(['id', 'name', 'is_active']);
        $users = User::where('status', 1)->orderBy('name')->get(['id', 'name']);

        return view('masters.departments.index', compact('departments', 'schools', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create($this->validated($request));

        session()->flash('alert-success', 'Department added.');

        return back();
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department));

        session()->flash('alert-success', 'Department updated.');

        return back();
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        session()->flash('alert-success', 'Department deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'name' => [
                'required', 'string', 'max:100',
                // The same name may exist in two schools, but only once within a school
                // (or once among the departments that serve every school).
                Rule::unique('departments', 'name')
                    ->where('school_id', $request->input('school_id') ?: null)
                    ->ignore($department?->id),
            ],
            'head_user_id' => 'nullable|integer|exists:users,id',
        ], [
            'name.unique' => 'This school already has a department with that name.',
        ]);

        $data['school_id'] = $data['school_id'] ?? null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
