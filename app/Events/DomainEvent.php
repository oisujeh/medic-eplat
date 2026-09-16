<?php

namespace App\Events;

use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Something clinically or administratively meaningful that has happened to a
 * record: a diagnosis coded, a result verified, a baby delivered.
 *
 * Events are published through App\Services\DomainEvents, never dispatched
 * directly: publishing writes the event to the journal first, then hands it
 * to whichever listeners consume it (surveillance today; data quality,
 * decision support and outbound integrations later). Listeners run inside
 * the publishing transaction, so a write that rolls back leaves no trace in
 * the journal and no side effects from its listeners.
 */
abstract class DomainEvent
{
    public readonly CarbonImmutable $occurredAt;

    public function __construct(public readonly ?User $actor = null)
    {
        $this->occurredAt = CarbonImmutable::now();
    }

    /**
     * The record the event is about.
     */
    abstract public function subject(): Model;

    /**
     * The facts of the event as they will be journaled, JSON-safe.
     *
     * @return array<string, mixed>
     */
    abstract public function payload(): array;

    /**
     * The patient whose record the event touches, when there is one.
     */
    public function patientId(): ?int
    {
        $subject = $this->subject();

        if ($subject instanceof Patient) {
            return (int) $subject->getKey();
        }

        $patientId = $subject->getAttribute('patient_id');

        return $patientId === null ? null : (int) $patientId;
    }

    /**
     * The journaled name of the event, e.g. "diagnosis_recorded".
     */
    public static function name(): string
    {
        return Str::snake(class_basename(static::class));
    }
}
