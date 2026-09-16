<?php

namespace App\Models\Concerns;

use App\Models\Patient;
use App\Services\AuditTrail;

/**
 * Records every create, update and delete of the model in the audit trail,
 * and lets the access-logging middleware recognise it as a patient record.
 *
 * Models whose patient is reached through a parent (a bill charge through its
 * bill, a claim line through its claim) override auditPatientId().
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => app(AuditTrail::class)->recordModelEvent('created', $model));
        static::updated(fn (self $model) => app(AuditTrail::class)->recordModelEvent('updated', $model));
        static::deleted(fn (self $model) => app(AuditTrail::class)->recordModelEvent('deleted', $model));
    }

    /**
     * The patient whose chart this record belongs to, if any.
     */
    public function auditPatientId(): ?int
    {
        if ($this instanceof Patient) {
            return (int) $this->getKey();
        }

        $patientId = $this->getAttributes()['patient_id'] ?? $this->getRawOriginal('patient_id');

        return $patientId === null ? null : (int) $patientId;
    }

    /**
     * Whether this record forms part of a patient's chart, and so has its
     * views logged as well as its changes.
     */
    public function isPatientRecord(): bool
    {
        return $this->auditPatientId() !== null;
    }

    /**
     * A short description of the record for the audit screen.
     */
    public function auditLabel(): string
    {
        return class_basename($this).' #'.$this->getKey();
    }

    /**
     * Attributes never written to the trail: timestamps carry no meaning and
     * hidden attributes (passwords, secrets) must not leak into it.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return array_values(array_unique(array_merge(
            ['created_at', 'updated_at', 'remember_token', 'password'],
            $this->getHidden(),
        )));
    }
}
