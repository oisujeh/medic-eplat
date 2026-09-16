<?php

namespace App\Models\Contracts;

/**
 * A model whose lifecycle is written to the audit trail.
 *
 * Implemented by the Auditable trait; the interface exists so the trail and
 * the access-logging middleware can recognise such models by type.
 */
interface AuditableRecord
{
    /**
     * The patient whose chart this record belongs to, if any.
     */
    public function auditPatientId(): ?int;

    /**
     * Whether this record forms part of a patient's chart.
     */
    public function isPatientRecord(): bool;

    /**
     * A short description of the record for the audit screen.
     */
    public function auditLabel(): string;

    /**
     * Attributes never written to the trail.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array;
}
