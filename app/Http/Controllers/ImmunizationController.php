<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\StoreImmunizationRequest;
use App\Models\Encounter;
use App\Models\Immunization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Immunizations administered during a nursing encounter.
 */
class ImmunizationController extends Controller
{
    use AssertsEncounterRecord;

    /**
     * Record a vaccine given.
     */
    public function store(StoreImmunizationRequest $request, Encounter $encounter): RedirectResponse
    {
        $encounter->patient->immunizations()->create([
            ...$request->validated(),
            'visit_id' => $encounter->visit_id,
            'encounter_id' => $encounter->id,
            'administered_by' => $request->user()->id,
            'administered_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Immunization recorded.']);

        return back();
    }

    /**
     * Remove an immunization from the patient's record.
     */
    public function destroy(Request $request, Encounter $encounter, Immunization $immunization): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        $this->assertBelongsToPatient($encounter, $immunization);

        $immunization->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Immunization removed.']);

        return back();
    }
}
