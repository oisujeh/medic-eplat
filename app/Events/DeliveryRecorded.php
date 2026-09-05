<?php

namespace App\Events;

use App\Models\Birth;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DeliveryRecorded extends DomainEvent
{
    public function __construct(public readonly Delivery $delivery, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->delivery;
    }

    public function payload(): array
    {
        $this->delivery->loadMissing('births');

        return [
            'pregnancy_id' => $this->delivery->pregnancy_id,
            'delivered_at' => $this->delivery->delivered_at->toIso8601String(),
            'mode' => $this->delivery->mode->value,
            'place' => $this->delivery->place,
            'maternal_outcome' => $this->delivery->maternal_outcome->value,
            'births' => $this->delivery->births->count(),
            'live_births' => $this->delivery->births->filter(fn (Birth $b) => $b->outcome->isLive())->count(),
        ];
    }
}
