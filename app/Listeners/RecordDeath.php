<?php

namespace App\Listeners;

use App\Events\PatientDied;
use App\Models\Death;

/**
 * Keeps the death register: whatever clinical record carried a death (a
 * discharge, a delivery) results in one death record on the patient.
 */
class RecordDeath
{
    public function handle(PatientDied $event): void
    {
        Death::query()->updateOrCreate(
            ['patient_id' => $event->patient->id],
            [
                'died_at' => $event->diedAt,
                'place' => Death::PLACE_FACILITY,
                'source_type' => $event->source->getMorphClass(),
                'source_id' => $event->source->getKey(),
                'certified_by' => $event->actor?->id,
                'recorded_by' => $event->actor?->id,
            ],
        );
    }
}
