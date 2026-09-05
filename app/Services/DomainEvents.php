<?php

namespace App\Services;

use App\Events\DomainEvent;
use App\Models\DomainEventRecord;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The one way a domain event enters the system.
 *
 * Publishing journals the event, then dispatches it to its listeners. Both
 * happen in the caller's transaction: if the write that caused the event
 * rolls back, so do the journal entry and everything the listeners did.
 */
class DomainEvents
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function publish(DomainEvent $event): DomainEventRecord
    {
        $subject = $event->subject();

        $record = new DomainEventRecord;
        $record->forceFill([
            'name' => $event::name(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'patient_id' => $event->patientId(),
            'actor_id' => $event->actor?->getKey(),
            'payload' => $event->payload(),
            'occurred_at' => $event->occurredAt,
        ]);
        $record->save();

        $this->dispatcher->dispatch($event);

        return $record;
    }
}
