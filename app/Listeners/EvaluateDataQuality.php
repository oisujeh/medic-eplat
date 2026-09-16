<?php

namespace App\Listeners;

use App\Events\DiagnosisRecorded;
use App\Events\DiagnosisRemoved;
use App\Events\EncounterSigned;
use App\Events\ObservationRecorded;
use App\Events\PatientRegistered;
use App\Events\PatientsMerged;
use App\Events\PatientUpdated;
use App\Models\Encounter;
use App\Services\DataQuality;

/**
 * Judges a record against the data-quality rules as it is written.
 *
 * Runs synchronously inside the publishing transaction, so an issue never
 * outlives the write that caused it, and a correction closes its issue in the
 * same request that made the correction. Only the rules that can decide there
 * and then run here; the ones that measure elapsed time wait for the sweep.
 */
class EvaluateDataQuality
{
    public function __construct(private readonly DataQuality $dataQuality) {}

    public function handlePatientRegistered(PatientRegistered $event): void
    {
        $this->dataQuality->evaluate($event->patient);
    }

    public function handlePatientUpdated(PatientUpdated $event): void
    {
        $this->dataQuality->evaluate($event->patient);
    }

    /**
     * Both folders are judged: the survivor because it has just taken on the
     * duplicate's details, and the duplicate because every rule passes a
     * merged folder, which closes what was open against it.
     */
    public function handlePatientsMerged(PatientsMerged $event): void
    {
        $this->dataQuality->evaluate($event->survivor);
        $this->dataQuality->evaluate($event->duplicate);
    }

    public function handleEncounterSigned(EncounterSigned $event): void
    {
        $this->dataQuality->evaluate($event->encounter);
    }

    /**
     * The diagnosis itself, and the encounter it was coded on: adding one is
     * what puts right an encounter signed without a diagnosis.
     */
    public function handleDiagnosisRecorded(DiagnosisRecorded $event): void
    {
        $this->dataQuality->evaluate($event->problem);

        $encounter = $event->problem->encounter;

        if ($encounter instanceof Encounter) {
            $this->dataQuality->evaluate($encounter);
        }
    }

    public function handleDiagnosisRemoved(DiagnosisRemoved $event): void
    {
        $encounter = $event->problem->encounter;

        if ($encounter instanceof Encounter) {
            $this->dataQuality->evaluate($encounter);
        }
    }

    public function handleObservationRecorded(ObservationRecorded $event): void
    {
        $this->dataQuality->evaluate($event->set);
    }
}
