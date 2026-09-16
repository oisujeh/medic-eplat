<?php

namespace App\Events;

use App\Models\Immunization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ImmunizationGiven extends DomainEvent
{
    public function __construct(public readonly Immunization $immunization, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->immunization;
    }

    public function payload(): array
    {
        return [
            'vaccine' => $this->immunization->vaccine,
            'dose_label' => $this->immunization->dose_label,
            'batch_no' => $this->immunization->batch_no,
            'administered_at' => $this->immunization->administered_at?->toIso8601String(),
            'encounter_id' => $this->immunization->encounter_id,
        ];
    }
}
