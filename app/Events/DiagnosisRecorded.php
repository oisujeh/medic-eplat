<?php

namespace App\Events;

use App\Models\Problem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A problem was added to, or re-coded on, the patient's problem list.
 */
class DiagnosisRecorded extends DomainEvent
{
    public function __construct(public readonly Problem $problem, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->problem;
    }

    public function payload(): array
    {
        return [
            'name' => $this->problem->name,
            'code' => $this->problem->code,
            'status' => $this->problem->status,
            'role' => $this->problem->role,
            'onset_date' => $this->problem->onset_date?->toDateString(),
            'encounter_id' => $this->problem->encounter_id,
        ];
    }
}
