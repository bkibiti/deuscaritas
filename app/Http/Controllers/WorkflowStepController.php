<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The approval chain: who signs the steps that belong to one named person (School
 * Coordinator, Manager, Director), and which steps are in use.
 */
class WorkflowStepController extends Controller
{
    public function index(): View
    {
        $steps = WorkflowStep::with('user')->orderBy('position')->get();
        $users = User::where('status', 1)->orderBy('name')->get(['id', 'name']);

        return view('masters.workflow_steps.index', compact('steps', 'users'));
    }

    public function update(Request $request, WorkflowStep $workflowStep): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 1)],
        ]);

        if ($workflowStep->hasNamedApprover()) {
            $workflowStep->user_id = $data['user_id'] ?? null;
        }

        $workflowStep->is_active = $request->boolean('is_active');
        $workflowStep->save();

        session()->flash('alert-success', $workflowStep->name.' updated.');

        return back();
    }
}
