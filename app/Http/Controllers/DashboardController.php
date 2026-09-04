<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * The home screen: today's figures, the user's worklists and alerts,
     * shaped by the modules they can reach.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $modules = $user->accessibleModules()->map(fn (Module $module) => $module->slug)->all();

        return Inertia::render('Dashboard', [
            'today' => Carbon::today()->isoFormat('dddd, D MMMM YYYY'),
            'home' => $this->dashboard->home($user, $modules),
        ]);
    }
}
