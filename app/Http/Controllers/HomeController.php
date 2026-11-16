<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;

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
     * The post-login landing page, for every logged-in user. For now it shows how much master
     * data is set up; the request overview replaces it when the workflow is built.
     */
    public function index(): Renderable
    {
        $counts = [
            ['label' => 'Schools', 'value' => School::where('is_active', true)->count(), 'route' => 'schools.index', 'permission' => 'View Schools'],
            ['label' => 'Departments', 'value' => Department::where('is_active', true)->count(), 'route' => 'departments.index', 'permission' => 'View Departments'],
            ['label' => 'Request categories', 'value' => RequestCategory::where('is_active', true)->count(), 'route' => 'request-categories.index', 'permission' => 'View Request Categories'],
            ['label' => 'Active users', 'value' => User::where('status', 1)->count(), 'route' => 'users.index', 'permission' => 'View Users'],
        ];

        return view('dashboard', compact('counts'));
    }
}
