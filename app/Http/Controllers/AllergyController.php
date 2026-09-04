<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\StoreAllergyRequest;
use App\Models\Allergy;
use App\Models\Encounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The patient's allergy list, maintained from within an encounter.
 */
class AllergyController extends Controller
{
    use AssertsEncounterRecord;

    /**
     * Record an allergy.
     */
    public function store(StoreAllergyRequest $request, Encounter $encounter): RedirectResponse
    {
        $encounter->patient->allergies()->create([
            ...$request->validated(),
            'status' => Allergy::STATUS_ACTIVE,
            'recorded_by' => $request->user()->id,
            'noted_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Allergy recorded.']);

        return back();
    }

    /**
     * Remove an allergy from the patient's record.
     */
    public function destroy(Request $request, Encounter $encounter, Allergy $allergy): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        $this->assertBelongsToPatient($encounter, $allergy);

        $allergy->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Allergy removed.']);

        return back();
    }
}
