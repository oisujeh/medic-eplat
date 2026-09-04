<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Encounter;
use Illuminate\Database\Eloquent\Model;

trait AssertsEncounterRecord
{
    /**
     * A record reached through an encounter must belong to that encounter's
     * patient; anything else is treated as not found.
     */
    protected function assertBelongsToPatient(Encounter $encounter, Model $record): void
    {
        abort_unless($record->getAttribute('patient_id') === $encounter->patient_id, 404);
    }
}
