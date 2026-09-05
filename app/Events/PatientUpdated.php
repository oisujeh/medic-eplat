<?php

namespace App\Events;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PatientUpdated extends DomainEvent
{
    /**
     * @param  list<string>  $changed  The attributes the update touched. Values are in the audit trail.
     */
    public function __construct(public readonly Patient $patient, public readonly array $changed, ?User $actor = null)
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
            'changed' => $this->changed,
        ];
    }
}
