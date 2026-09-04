<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacilityProfileRequest;
use App\Services\FacilitySettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Revising the facility profile after the first-run wizard.
 */
class FacilityController extends Controller
{
    public function __construct(private readonly FacilitySettings $facility) {}

    /**
     * The facility profile form.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/Facility', [
            'profile' => $this->facility->profile(),
        ]);
    }

    /**
     * Save changes to the facility profile.
     */
    public function update(FacilityProfileRequest $request): RedirectResponse
    {
        $this->facility->save($request->profile());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Facility profile saved.']);

        return back();
    }
}
