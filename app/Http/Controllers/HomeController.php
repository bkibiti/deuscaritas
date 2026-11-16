<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\School;
use App\Models\SchoolRequest;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // login form
    public function login()
    {
        return view('auth.login');
    }

    /**
     * The post-login landing page, for every logged-in user: what is waiting for them, their
     * own open requests, and how much master data is set up.
     */
    public function index(Request $request): Renderable
    {
        $user = $request->user();
        $open = [SchoolRequest::STATUS_PENDING, SchoolRequest::STATUS_RETURNED, SchoolRequest::STATUS_APPROVED, SchoolRequest::STATUS_IN_PROGRESS];

        $mine = [
            ['label' => 'Awaiting my action', 'value' => SchoolRequest::awaitingActionBy($user)->count(), 'url' => route('requests.index', ['scope' => 'inbox'])],
            ['label' => 'My open requests', 'value' => SchoolRequest::where('requester_id', $user->id)->whereIn('status', $open)->count(), 'url' => route('requests.index')],
            ['label' => 'Returned to me', 'value' => SchoolRequest::where('requester_id', $user->id)->where('status', SchoolRequest::STATUS_RETURNED)->count(), 'url' => route('requests.index', ['status' => SchoolRequest::STATUS_RETURNED])],
            ['label' => 'Assigned to me', 'value' => SchoolRequest::where('assigned_to', $user->id)->where('status', SchoolRequest::STATUS_IN_PROGRESS)->count(), 'url' => null],
        ];

        $counts = [
            ['label' => 'Schools', 'value' => School::where('is_active', true)->count(), 'route' => 'schools.index', 'permission' => 'View Schools'],
            ['label' => 'Departments', 'value' => Department::where('is_active', true)->count(), 'route' => 'departments.index', 'permission' => 'View Departments'],
            ['label' => 'Request categories', 'value' => RequestCategory::where('is_active', true)->count(), 'route' => 'request-categories.index', 'permission' => 'View Request Categories'],
            ['label' => 'Active users', 'value' => User::where('status', 1)->count(), 'route' => 'users.index', 'permission' => 'View Users'],
        ];

        return view('dashboard', compact('mine', 'counts'));
    }
}
