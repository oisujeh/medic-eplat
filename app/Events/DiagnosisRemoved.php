<?php

namespace App\Events;

use App\Models\Problem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A problem is being taken off the problem list. Published before the row
 * is deleted so listeners can still read it.
 */
class DiagnosisRemoved extends DomainEvent
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
            'encounter_id' => $this->problem->encounter_id,
        ];
    }
}
