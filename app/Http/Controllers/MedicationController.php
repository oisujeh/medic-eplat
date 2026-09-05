<?php

namespace App\Http\Controllers;

use App\Enums\ConceptClass;
use App\Events\MedicationPrescribed;
use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\MedicationRequest;
use App\Models\Encounter;
use App\Models\Medication;
use App\Services\DomainEvents;
use App\Services\Terminology;
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
    public function store(MedicationRequest $request, Encounter $encounter, DomainEvents $events, Terminology $terminology): RedirectResponse
    {
        $data = $request->validated();

        $medication = $encounter->patient->medications()->create([
            ...$data,
            'concept_id' => $terminology->code(ConceptClass::Drug, $data['concept_id'] ?? null, $data['name']),
            'status' => Medication::STATUS_ACTIVE,
            'visit_id' => $encounter->visit_id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $request->user()->id,
            'started_at' => now(),
        ]);

        $events->publish(new MedicationPrescribed($medication, $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medication prescribed.']);

        return back();
    }

    /**
     * Update an existing medication.
     */
    public function update(MedicationRequest $request, Encounter $encounter, Medication $medication, Terminology $terminology): RedirectResponse
    {
        $this->assertBelongsToPatient($encounter, $medication);

        $data = $request->validated();

        $medication->update([
            ...$data,
            'concept_id' => $terminology->code(ConceptClass::Drug, $data['concept_id'] ?? null, $data['name']),
        ]);

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
