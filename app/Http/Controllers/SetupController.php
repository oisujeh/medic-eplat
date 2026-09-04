<?php

namespace App\Http\Controllers;

use App\Http\Requests\FacilityProfileRequest;
use App\Services\FacilitySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The first-run wizard that captures the facility profile before the system
 * is used.
 */
class SetupController extends Controller
{
    public function __construct(private readonly FacilitySettings $facility) {}

    /**
     * The wizard itself. Once setup is complete the facility profile is
     * revised from Administration instead.
     */
    public function show(): Response|RedirectResponse
    {
        if ($this->facility->isConfigured()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('setup/Wizard', [
            'profile' => $this->facility->profile(),
        ]);
    }

    /**
     * Save the facility profile and open the system.
     */
    public function store(FacilityProfileRequest $request): RedirectResponse
    {
        $profile = $request->profile();

        $this->facility->complete($profile);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Facility set up. Welcome to {$profile['name']}."]);

        return redirect()->route('dashboard');
    }

    /**
     * The holding page shown to staff who cannot complete the wizard.
     */
    public function pending(Request $request): Response|RedirectResponse
    {
        if ($this->facility->isConfigured()) {
            return redirect()->route('dashboard');
        }

        if ($request->user()?->canAccessModule('administration')) {
            return redirect()->route('setup.show');
        }

        return Inertia::render('setup/Pending');
    }
}
