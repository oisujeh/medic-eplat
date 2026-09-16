<?php

namespace App\Events;

use App\Models\Encounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EncounterSigned extends DomainEvent
{
    public function __construct(public readonly Encounter $encounter, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->encounter;
    }

    public function payload(): array
    {
        return [
            'type' => $this->encounter->type->value,
            'outcome' => $this->encounter->outcome?->value,
            'visit_id' => $this->encounter->visit_id,
            'service_point_id' => $this->encounter->service_point_id,
            'follow_up_at' => $this->encounter->follow_up_at?->toIso8601String(),
        ];
    }
}
