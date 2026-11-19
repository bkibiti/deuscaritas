<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * The approval chain: which role signs each step, and which steps are in use. Who signs is
 * decided by who holds the role, so changing people never means editing the chain.
 */
class WorkflowStepController extends Controller
{
    public function index(): View
    {
        $steps = WorkflowStep::with('role')->orderBy('position')->get();
        $roles = Role::orderBy('name')->get(['id', 'name']);

        // Who holds each step's role now, to show who would sign.
        $holders = User::where('status', 1)->with(['roles:id', 'school:id,name'])->orderBy('name')->get(['id', 'name', 'school_id']);

        return view('masters.workflow_steps.index', compact('steps', 'roles', 'holders'));
    }

    public function update(Request $request, WorkflowStep $workflowStep): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $workflowStep->role_id = $data['role_id'] ?? null;
        $workflowStep->is_active = $request->boolean('is_active');
        $workflowStep->save();

        session()->flash('alert-success', $workflowStep->name.' updated.');

        return back();
    }
}
