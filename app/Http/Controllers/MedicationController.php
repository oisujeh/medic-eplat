<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\MedicationRequest;
use App\Models\Encounter;
use App\Models\Medication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Prescriptions written from within an encounter.
 */
class MedicationController extends Controller
{
    use AssertsEncounterRecord;

    /**
     * Prescribe a medication.
     */
    public function store(MedicationRequest $request, Encounter $encounter): RedirectResponse
    {
        $encounter->patient->medications()->create([
            ...$request->validated(),
            'status' => Medication::STATUS_ACTIVE,
            'visit_id' => $encounter->visit_id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $request->user()->id,
            'started_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medication prescribed.']);

        return back();
    }

    /**
     * Update an existing medication.
     */
    public function update(MedicationRequest $request, Encounter $encounter, Medication $medication): RedirectResponse
    {
        $this->assertBelongsToPatient($encounter, $medication);

        $medication->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medication updated.']);

        return back();
    }

    /**
     * Stop an active medication.
     */
    public function stop(Request $request, Encounter $encounter, Medication $medication): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        $this->assertBelongsToPatient($encounter, $medication);

        $medication->update(['status' => Medication::STATUS_STOPPED, 'stopped_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medication stopped.']);

        return back();
    }

    /**
     * Remove a medication from the patient's list.
     */
    public function destroy(Request $request, Encounter $encounter, Medication $medication): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        $this->assertBelongsToPatient($encounter, $medication);

        $medication->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medication removed.']);

        return back();
    }
}
