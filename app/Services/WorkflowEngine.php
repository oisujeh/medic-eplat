<?php

namespace App\Services;

use App\Enums\EncounterType;
use App\Models\Encounter;
use App\Models\QueueEntry;
use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\Visit;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use Illuminate\Support\Collection;

/**
 * Reads the pathway a visit follows and answers the questions the flow
 * asks of it: which pathway a new visit takes, which step an entry is,
 * what encounter opens there, what fee it posts, and where the patient
 * may go next.
 *
 * A visit without a pathway, or a step without transitions, keeps the
 * behaviour the system had before pathways existed: any active service
 * point may follow, and a consultation posts the consultation fee.
 */
class WorkflowEngine
{
    /**
     * The pathway a visit starting at a service point should follow: the
     * one chosen at the desk, else the first active pathway that starts
     * there (the default first), else the default pathway.
     */
    public function forEntry(ServicePoint $entry, ?Workflow $chosen = null): ?Workflow
    {
        if ($chosen !== null) {
            return $chosen;
        }

        $workflows = Workflow::query()
            ->active()
            ->with('stages')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get();

        return $workflows->first(fn (Workflow $w) => $w->startsAt($entry))
            ?? $workflows->firstWhere('is_default', true);
    }

    /**
     * The step a visit is at when it stands at a service point.
     */
    public function stageFor(?Visit $visit, ?ServicePoint $servicePoint): ?WorkflowStage
    {
        if ($visit === null || $servicePoint === null || $visit->workflow_id === null) {
            return null;
        }

        $visit->loadMissing('workflow.stages');

        return $visit->workflow?->stageAt($servicePoint);
    }

    /**
     * The encounter type opened for a queue entry.
     */
    public function encounterTypeFor(QueueEntry $entry): EncounterType
    {
        $entry->loadMissing('servicePoint', 'visit');

        return $this->stageFor($entry->visit, $entry->servicePoint)->encounter_type
            ?? EncounterType::forServicePoint(
                (string) $entry->servicePoint?->module_slug,
                (string) $entry->servicePoint?->slug,
            );
    }

    /**
     * The fee-schedule code to post when an encounter is signed: the step's
     * own, or the consultation fee where no pathway says otherwise.
     */
    public function chargeCodeFor(Encounter $encounter): ?string
    {
        $encounter->loadMissing('visit', 'servicePoint');
        $stage = $this->stageFor($encounter->visit, $encounter->servicePoint);

        if ($stage !== null) {
            return $stage->charge_code;
        }

        return $encounter->type->isConsultation() ? ServiceCharge::CONSULTATION : null;
    }

    /**
     * Where a patient may be sent from a service point on their visit's
     * pathway, suggested step first. Null when the pathway does not
     * restrict it (no pathway, no step, or a step with no transitions).
     *
     * @return Collection<int, ServicePoint>|null
     */
    public function nextOptions(?Visit $visit, ?ServicePoint $from): ?Collection
    {
        $stage = $this->stageFor($visit, $from);

        if ($stage === null) {
            return null;
        }

        $transitions = $stage->transitions()->with('to.servicePoint')->get();

        if ($transitions->isEmpty()) {
            return null;
        }

        $points = [];

        foreach ($transitions as $transition) {
            /** @var WorkflowTransition $transition */
            $servicePoint = $transition->to->servicePoint;

            if ($servicePoint === null || ! $servicePoint->is_active) {
                continue;
            }

            $servicePoint->setAttribute('is_default', $transition->is_default);
            $points[] = $servicePoint;
        }

        return collect($points);
    }

    /**
     * Whether the pathway allows a move between two service points.
     */
    public function allows(?Visit $visit, ?ServicePoint $from, ServicePoint $to): bool
    {
        $options = $this->nextOptions($visit, $from);

        return $options === null || $options->contains('id', $to->id);
    }
}
