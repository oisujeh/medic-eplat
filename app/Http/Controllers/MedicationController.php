<?php

namespace App\Http\Controllers;

use App\Enums\ConceptClass;
use App\Events\MedicationPrescribed;
use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\MedicationRequest;
use App\Models\Encounter;
use App\Models\InventoryItem;
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
        $data = $this->coded($request->validated(), $terminology);

        $medication = $encounter->patient->medications()->create([
            ...$data,
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

        $medication->update($this->coded($request->validated(), $terminology));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medication updated.']);

        return back();
    }

    /**
     * Code the entry: the concept chosen or matched by name, and the drug
     * concept of the chosen product when the prescriber picked one without
     * naming a concept.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function coded(array $data, Terminology $terminology): array
    {
        $product = isset($data['inventory_item_id']) ? InventoryItem::find((int) $data['inventory_item_id']) : null;

        $data['inventory_item_id'] = $product?->id;
        $data['concept_id'] = $terminology->code(ConceptClass::Drug, $data['concept_id'] ?? null, $data['name'])
            ?? $product?->concept_id;

        return $data;
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
