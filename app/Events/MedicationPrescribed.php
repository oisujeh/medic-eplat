<?php

namespace App\Events;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MedicationPrescribed extends DomainEvent
{
    public function __construct(public readonly Medication $medication, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->medication;
    }

    public function payload(): array
    {
        return [
            'name' => $this->medication->name,
            'dose' => $this->medication->dose,
            'frequency' => $this->medication->frequency,
            'route' => $this->medication->route,
            'encounter_id' => $this->medication->encounter_id,
        ];
    }
}
