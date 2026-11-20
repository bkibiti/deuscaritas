<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\DashboardData;
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
     * The post-login landing page, for every logged-in user. Everyone sees their own requests;
     * people who sign or oversee requests also get the overview, and stock keepers the stock.
     */
    public function index(Request $request): Renderable
    {
        $user = $request->user();

        $dashboard = (new DashboardData($user, $request->query('period'), $request->integer('school') ?: null))->build();

        return view('dashboard', $dashboard + [
            'schools' => School::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
