<?php

namespace App\Listeners;

use App\Events\DiagnosisRecorded;
use App\Events\DiagnosisRemoved;
use App\Services\CaseSurveillance;

/**
 * Keeps the IDSR register in step with the problem list: a coded diagnosis
 * is screened against the notifiable-disease catalogue, and a removed one
 * releases the case it opened unless the DSNO was already told.
 *
 * Runs synchronously so the clinician is warned in the same request that
 * coded the diagnosis.
 */
class ScreenDiagnosesForSurveillance
{
    public function __construct(private readonly CaseSurveillance $surveillance) {}

    public function handleRecorded(DiagnosisRecorded $event): void
    {
        $this->surveillance->screen($event->problem, $event->actor);
    }

    public function handleRemoved(DiagnosisRemoved $event): void
    {
        $this->surveillance->forget($event->problem);
    }
}
