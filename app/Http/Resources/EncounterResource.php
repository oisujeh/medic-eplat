<?php

namespace App\Http\Resources;

use App\Models\Encounter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An encounter being documented: identity, narrative and the routes that act
 * on it.
 *
 * @mixin Encounter
 */
class EncounterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_open' => $this->isOpen(),
            'service_point' => $this->servicePoint?->name,
            'service_slug' => $this->servicePoint?->slug,
            'captures_observations' => (bool) ($this->servicePoint?->captures_vitals ?? false),
            'visit_number' => $this->visit?->visit_number,
            'visit_date' => ($this->visit?->opened_at ?? $this->started_at)?->isoFormat('D MMM YYYY'),
            'author' => $this->author?->name,
            'started_at' => $this->started_at?->toIso8601String(),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_at_label' => $this->signed_at?->isoFormat('D MMM YYYY, h:mm a'),
            'presenting_complaint' => $this->presenting_complaint,
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'structured' => $this->structured,
            'outcome' => $this->outcome?->value,
            'follow_up_at' => $this->follow_up_at?->toIso8601String(),
            'urls' => [
                'show' => route('encounters.show', $this->id),
                'update' => route('encounters.update', $this->id),
                'sign' => route('encounters.sign', $this->id),
                'follow_up' => route('encounters.follow-up', $this->id),
                'addenda' => route('encounters.addenda.store', $this->id),
                'problems' => route('encounters.problems.store', $this->id),
                'medications' => route('encounters.medications.store', $this->id),
                'allergies' => route('encounters.allergies.store', $this->id),
                'lab_orders' => route('encounters.lab-orders.store', $this->id),
                'immunizations' => route('encounters.immunizations.store', $this->id),
                'observations' => route('patients.observations.store', $this->patient_id),
                'console' => $this->type->isConsultation() ? route('clinical.index') : route('nursing.index'),
            ],
        ];
    }
}
