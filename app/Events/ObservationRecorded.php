<?php

namespace App\Events;

use App\Models\Observation;
use App\Models\ObservationSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A set of readings (vitals, anthropometrics, antenatal findings) was taken.
 */
class ObservationRecorded extends DomainEvent
{
    public function __construct(public readonly ObservationSet $set, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->set;
    }

    public function payload(): array
    {
        $this->set->loadMissing('observations');

        return [
            'alert_level' => $this->set->alert_level->value,
            'encounter_id' => $this->set->encounter_id,
            'admission_id' => $this->set->admission_id,
            'readings' => $this->set->observations
                ->mapWithKeys(fn (Observation $o) => [$o->code->value => $o->text_value ?? $o->value])
                ->all(),
        ];
    }
}
