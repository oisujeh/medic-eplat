<?php

namespace App\Events;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PatientRegistered extends DomainEvent
{
    public function __construct(public readonly Patient $patient, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->patient;
    }

    public function payload(): array
    {
        return [
            'file_number' => $this->patient->file_number,
            'sex' => $this->patient->sex,
            'date_of_birth' => $this->patient->date_of_birth?->toDateString(),
            'state' => $this->patient->state,
            'lga' => $this->patient->lga,
        ];
    }
}
