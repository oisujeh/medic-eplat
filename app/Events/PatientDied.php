<?php

namespace App\Events;

use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A death was recorded: today on discharge from a ward or as a maternal
 * outcome at delivery. The record that carried the death is kept as the
 * source so a later death register can be built from the journal.
 */
class PatientDied extends DomainEvent
{
    public function __construct(
        public readonly Patient $patient,
        public readonly Model $source,
        public readonly CarbonInterface $diedAt,
        ?User $actor = null,
    ) {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->patient;
    }

    public function payload(): array
    {
        return [
            'died_at' => $this->diedAt->toIso8601String(),
            'source_type' => $this->source->getMorphClass(),
            'source_id' => $this->source->getKey(),
        ];
    }
}
