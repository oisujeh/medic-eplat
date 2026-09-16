<?php

namespace App\Services;

use App\Enums\AlertLevel;
use App\Enums\TimelineKind;
use App\Models\Admission;
use App\Models\Delivery;
use App\Models\Dispense;
use App\Models\DispenseItem;
use App\Models\Encounter;
use App\Models\FormSubmission;
use App\Models\Immunization;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Medication;
use App\Models\Observation;
use App\Models\ObservationSet;
use App\Models\Patient;
use App\Models\Problem;
use App\Models\Referral;
use App\Models\SurveillanceCase;
use App\Support\TimelineEntry;
use Illuminate\Support\Collection;

/**
 * The patient's history as one dated feed: every encounter, diagnosis,
 * reading, result, prescription, admission, delivery, referral and case in
 * the order it happened, so a clinician can read the story without opening
 * each record. A read model over the existing tables; nothing is stored.
 */
class PatientTimeline
{
    public const DEFAULT_LIMIT = 200;

    /**
     * The timeline as the client renders it: the entries (newest first,
     * limited) and every kind with how many entries it holds, for filtering.
     *
     * @param  list<string>|null  $kinds  Kind values to keep; null for all.
     * @return array{entries: array<int, array<string, mixed>>, kinds: array<int, array{value: string, label: string, icon: string, count: int}>}
     */
    public function present(Patient $patient, ?array $kinds = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $all = $this->entries($patient);
        $counts = $all->countBy(fn (TimelineEntry $e) => $e->kind->value);

        $entries = $all
            ->when($kinds !== null, fn (Collection $c) => $c->filter(fn (TimelineEntry $e) => in_array($e->kind->value, $kinds, true)))
            ->take($limit)
            ->map(fn (TimelineEntry $e) => $e->toArray())
            ->values()
            ->all();

        $kindOptions = collect(TimelineKind::definitions())
            ->map(fn (array $k) => [...$k, 'count' => (int) ($counts[$k['value']] ?? 0)])
            ->filter(fn (array $k) => $k['count'] > 0)
            ->values()
            ->all();

        return ['entries' => $entries, 'kinds' => $kindOptions];
    }

    /**
     * Every entry for the patient, newest first.
     *
     * @return Collection<int, TimelineEntry>
     */
    public function entries(Patient $patient): Collection
    {
        $patient->loadMissing('registeredBy:id,name', 'death');

        return collect()
            ->merge($this->registration($patient))
            ->merge($this->encounters($patient))
            ->merge($this->forms($patient))
            ->merge($this->diagnoses($patient))
            ->merge($this->observations($patient))
            ->merge($this->laboratory($patient))
            ->merge($this->medications($patient))
            ->merge($this->dispenses($patient))
            ->merge($this->immunizations($patient))
            ->merge($this->admissions($patient))
            ->merge($this->deliveries($patient))
            ->merge($this->referrals($patient))
            ->merge($this->surveillance($patient))
            ->merge($this->death($patient))
            ->sortByDesc(fn (TimelineEntry $e) => [$e->at->getTimestamp(), $e->key])
            ->values();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function registration(Patient $patient): array
    {
        if ($patient->created_at === null) {
            return [];
        }

        return [new TimelineEntry(
            kind: TimelineKind::Registration,
            at: $patient->created_at,
            title: 'Registered',
            detail: "File number {$patient->file_number}",
            by: $patient->registeredBy?->name,
            key: 'patient:'.$patient->id,
        )];
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function encounters(Patient $patient): array
    {
        return $patient->encounters()
            ->with(['servicePoint:id,name', 'author:id,name', 'codedDiagnoses'])
            ->get()
            ->map(fn (Encounter $e) => new TimelineEntry(
                kind: TimelineKind::Encounter,
                at: $e->signed_at ?? $e->started_at ?? $e->created_at,
                title: trim($e->type->label().($e->servicePoint ? ' · '.$e->servicePoint->name : '')),
                detail: $e->diagnosisSummary() ?? $e->presenting_complaint,
                badge: $e->isSigned() ? null : 'In progress',
                tone: $e->isSigned() ? 'muted' : 'amber',
                href: route('encounters.show', $e),
                by: $e->author?->name,
                key: 'encounter:'.$e->id,
            ))
            ->all();
    }

    /**
     * Clinical forms filled for the patient: completed ones dated when they
     * were completed, drafts flagged as still in progress. Voided forms are
     * not history.
     *
     * @return array<int, TimelineEntry>
     */
    private function forms(Patient $patient): array
    {
        return $patient->formSubmissions()
            ->live()
            ->with(['form:id,name', 'version:id,version', 'filledBy:id,name', 'completedBy:id,name'])
            ->get()
            ->map(fn (FormSubmission $s) => new TimelineEntry(
                kind: TimelineKind::Form,
                at: $s->completed_at ?? $s->last_saved_at ?? $s->started_at,
                title: $s->form->name,
                detail: $s->version ? 'Version '.$s->version->version.' · '.$s->entry_mode->label() : null,
                badge: $s->isCompleted() ? null : 'Draft',
                tone: $s->isCompleted() ? 'muted' : 'amber',
                href: route('forms.show', $s),
                by: ($s->completedBy ?? $s->filledBy)?->name,
                key: 'form_submission:'.$s->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function diagnoses(Patient $patient): array
    {
        return $patient->problems()
            ->with('recordedBy:id,name')
            ->get()
            ->map(fn (Problem $p) => new TimelineEntry(
                kind: TimelineKind::Diagnosis,
                at: $p->created_at,
                title: trim(($p->code ? $p->code.' ' : '').$p->name),
                detail: collect([$p->role ? ucfirst($p->role) : null, $p->onset_date ? 'onset '.$p->onset_date->isoFormat('D MMM YYYY') : null])->filter()->implode(' · ') ?: null,
                badge: ucfirst($p->status),
                tone: $p->status === Problem::STATUS_RESOLVED ? 'green' : ($p->status === Problem::STATUS_CHRONIC ? 'violet' : 'muted'),
                href: $p->encounter_id ? route('encounters.show', $p->encounter_id) : null,
                by: $p->recordedBy?->name,
                key: 'problem:'.$p->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function observations(Patient $patient): array
    {
        return $patient->observationSets()
            ->with(['observations', 'recordedBy:id,name'])
            ->get()
            ->map(function (ObservationSet $set) {
                $readings = $set->observations
                    ->map(fn (Observation $o) => $o->code->shortLabel().' '.$o->display())
                    ->implode(' · ');

                return new TimelineEntry(
                    kind: TimelineKind::Observation,
                    at: $set->recorded_at,
                    title: 'Observations recorded',
                    detail: $readings !== '' ? $readings : null,
                    badge: $set->alert_level === AlertLevel::Normal ? null : ucfirst($set->alert_level->value),
                    tone: match ($set->alert_level) {
                        AlertLevel::Critical => 'red',
                        AlertLevel::Warning => 'amber',
                        default => 'muted',
                    },
                    href: $set->encounter_id ? route('encounters.show', $set->encounter_id) : null,
                    by: $set->recordedBy?->name,
                    key: 'observation_set:'.$set->id,
                );
            })
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function laboratory(Patient $patient): array
    {
        $entries = [];

        $orders = $patient->labOrders()->with(['results', 'orderedBy:id,name', 'verifiedBy:id,name'])->get();

        foreach ($orders as $order) {
            /** @var LabOrder $order */
            $entries[] = new TimelineEntry(
                kind: TimelineKind::LabOrder,
                at: $order->created_at,
                title: 'Lab order '.$order->accession_number,
                detail: $order->results->pluck('name')->implode(', ') ?: null,
                badge: $order->status->label(),
                tone: $order->status->tone(),
                href: $order->encounter_id ? route('encounters.show', $order->encounter_id) : null,
                by: $order->orderedBy?->name,
                key: 'lab_order:'.$order->id,
            );

            if ($order->verified_at === null) {
                continue;
            }

            $abnormal = $order->results->filter(fn (LabResult $r) => in_array($r->flag, ['low', 'high', 'critical', 'abnormal'], true))->count();

            $entries[] = new TimelineEntry(
                kind: TimelineKind::LabResult,
                at: $order->verified_at,
                title: 'Results released · '.$order->accession_number,
                detail: $order->results
                    ->map(fn (LabResult $r) => trim($r->name.' '.$r->displayValue().($r->flag && $r->flag !== 'normal' ? ' ('.$r->flag.')' : '')))
                    ->implode(' · ') ?: null,
                badge: $abnormal > 0 ? "{$abnormal} abnormal" : 'All within range',
                tone: $abnormal > 0 ? 'red' : 'green',
                href: $order->encounter_id ? route('encounters.show', $order->encounter_id) : null,
                by: $order->verifiedBy?->name,
                key: 'lab_result:'.$order->id,
            );
        }

        return $entries;
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function medications(Patient $patient): array
    {
        return $patient->medications()
            ->with('prescribedBy:id,name')
            ->get()
            ->map(fn (Medication $m) => new TimelineEntry(
                kind: TimelineKind::Medication,
                at: $m->created_at,
                title: $m->label(),
                detail: collect([$m->route, $m->stopped_at ? 'stopped '.$m->stopped_at->isoFormat('D MMM YYYY') : null])->filter()->implode(' · ') ?: null,
                badge: ucfirst($m->status),
                tone: $m->status === Medication::STATUS_ACTIVE ? 'blue' : 'muted',
                href: $m->encounter_id ? route('encounters.show', $m->encounter_id) : null,
                by: $m->prescribedBy?->name,
                key: 'medication:'.$m->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function dispenses(Patient $patient): array
    {
        return $patient->dispenses()
            ->with(['items', 'dispensedBy:id,name'])
            ->get()
            ->map(fn (Dispense $d) => new TimelineEntry(
                kind: TimelineKind::Dispense,
                at: $d->created_at,
                title: 'Medicines dispensed',
                detail: $d->items->map(fn (DispenseItem $i) => "{$i->name} × {$i->quantity}")->implode(' · ') ?: null,
                badge: $d->status === Dispense::STATUS_DISPENSED ? null : ucfirst($d->status),
                by: $d->dispensedBy?->name,
                key: 'dispense:'.$d->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function immunizations(Patient $patient): array
    {
        return $patient->immunizations()
            ->with('administeredBy:id,name')
            ->get()
            ->map(fn (Immunization $i) => new TimelineEntry(
                kind: TimelineKind::Immunization,
                at: $i->administered_at ?? $i->created_at,
                title: trim($i->vaccine.($i->dose_label ? ' · '.$i->dose_label : '')),
                detail: collect([$i->site, $i->route, $i->batch_no ? 'batch '.$i->batch_no : null])->filter()->implode(' · ') ?: null,
                href: $i->encounter_id ? route('encounters.show', $i->encounter_id) : null,
                by: $i->administeredBy?->name,
                key: 'immunization:'.$i->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function admissions(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->admissions()->with(['ward:id,name', 'admittedBy:id,name', 'dischargedBy:id,name'])->get() as $admission) {
            /** @var Admission $admission */
            $entries[] = new TimelineEntry(
                kind: TimelineKind::Admission,
                at: $admission->admitted_at ?? $admission->created_at,
                title: $admission->ward ? 'Admitted to '.$admission->ward->name : 'Admission requested',
                detail: $admission->admitting_diagnosis,
                badge: $admission->status->label(),
                tone: $admission->isActive() ? 'blue' : 'muted',
                href: route('admissions.show', $admission),
                by: $admission->admittedBy?->name,
                key: 'admission:'.$admission->id,
            );

            if ($admission->discharged_at !== null) {
                $entries[] = new TimelineEntry(
                    kind: TimelineKind::Discharge,
                    at: $admission->discharged_at,
                    title: $admission->discharge_type?->label() ?? 'Discharged',
                    detail: $admission->discharge_summary,
                    badge: $admission->lengthOfStayDays() !== null ? $admission->lengthOfStayDays().' day'.($admission->lengthOfStayDays() === 1 ? '' : 's') : null,
                    href: route('admissions.show', $admission),
                    by: $admission->dischargedBy?->name,
                    key: 'discharge:'.$admission->id,
                );
            }
        }

        return $entries;
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function deliveries(Patient $patient): array
    {
        return Delivery::query()
            ->where('patient_id', $patient->id)
            ->with(['births', 'attendant:id,name'])
            ->get()
            ->map(fn (Delivery $d) => new TimelineEntry(
                kind: TimelineKind::Delivery,
                at: $d->delivered_at,
                title: 'Delivery · '.$d->mode->label(),
                detail: $d->births->count().' '.($d->births->count() === 1 ? 'baby' : 'babies').' · maternal outcome: '.$d->maternal_outcome->label(),
                badge: $d->complications ? count($d->complications).' complication'.(count($d->complications) === 1 ? '' : 's') : null,
                tone: $d->complications ? 'amber' : 'muted',
                href: route('maternity.show', $d->pregnancy_id),
                by: $d->attendant?->name,
                key: 'delivery:'.$d->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function referrals(Patient $patient): array
    {
        return $patient->referrals()
            ->with('referredBy:id,name')
            ->get()
            ->map(fn (Referral $r) => new TimelineEntry(
                kind: TimelineKind::Referral,
                at: $r->referred_at,
                title: 'Referred to '.$r->destination_facility,
                detail: $r->reason,
                badge: $r->status->label(),
                tone: $r->status->tone(),
                href: route('referrals.show', $r),
                by: $r->referredBy?->name,
                key: 'referral:'.$r->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function surveillance(Patient $patient): array
    {
        return SurveillanceCase::query()
            ->where('patient_id', $patient->id)
            ->with(['disease:id,name', 'detectedBy:id,name'])
            ->get()
            ->map(fn (SurveillanceCase $c) => new TimelineEntry(
                kind: TimelineKind::Surveillance,
                at: $c->detected_at,
                title: 'IDSR case · '.$c->disease->name,
                detail: $c->category->label().' · '.$c->notification_status->label(),
                badge: $c->classification->label(),
                tone: $c->classification->isOpen() ? 'red' : 'muted',
                href: route('surveillance.show', $c),
                by: $c->detectedBy?->name,
                key: 'surveillance_case:'.$c->id,
            ))
            ->all();
    }

    /**
     * @return array<int, TimelineEntry>
     */
    private function death(Patient $patient): array
    {
        $death = $patient->death;

        if ($death === null) {
            return [];
        }

        return [new TimelineEntry(
            kind: TimelineKind::Death,
            at: $death->died_at,
            title: 'Died',
            detail: collect([ucfirst($death->place), $death->cause])->filter()->implode(' · ') ?: null,
            tone: 'red',
            key: 'death:'.$death->id,
        )];
    }
}
