<?php

namespace App\Events;

use App\Models\LabResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A result line was verified and released to the chart. One event per
 * analyte, so a listener can match on a single test.
 */
class LabResultVerified extends DomainEvent
{
    public function __construct(public readonly LabResult $result, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->result;
    }

    public function payload(): array
    {
        return [
            'lab_order_id' => $this->result->lab_order_id,
            'lab_test_id' => $this->result->lab_test_id,
            'name' => $this->result->name,
            'code' => $this->result->code,
            'value' => $this->result->value,
            'unit' => $this->result->unit,
            'flag' => $this->result->flag,
            'encounter_id' => $this->result->encounter_id,
        ];
    }
}
