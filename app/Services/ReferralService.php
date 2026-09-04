<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\ReferralStatus;
use App\Models\Allergy;
use App\Models\Encounter;
use App\Models\Medication;
use App\Models\Observation;
use App\Models\ObservationSet;
use App\Models\Problem;
use App\Models\Referral;
use App\Models\User;
use App\Support\PatientOptions;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Outbound referrals: the record, its number, its lifecycle and the letter.
 */
class ReferralService
{
    public function __construct(private readonly FacilitySettings $facility) {}

    /**
     * What the referral form is pre-filled with from the encounter: the
     * primary diagnosis, the assessment and plan, and the medicines given.
     *
     * @return array{diagnosis: string|null, clinical_summary: string|null, treatment_given: string|null}
     */
    public function draftFor(Encounter $encounter): array
    {
        $primary = Problem::query()
            ->where('encounter_id', $encounter->id)
            ->orderByRaw("case role when 'primary' then 0 when 'secondary' then 1 else 2 end")
            ->orderBy('id')
            ->first();

        $summary = collect([
            $encounter->presenting_complaint ? 'Presenting complaint: '.$encounter->presenting_complaint : null,
            $encounter->assessment ? 'Assessment: '.$encounter->assessment : null,
            $encounter->plan ? 'Plan: '.$encounter->plan : null,
        ])->filter()->implode("\n");

        $treatment = $encounter->medications()
            ->get()
            ->map(fn (Medication $m) => trim(collect([$m->name, $m->dose, $m->route, $m->frequency])->filter()->implode(' ')))
            ->implode("\n");

        return [
            'diagnosis' => $primary ? trim($primary->name.($primary->code ? " ({$primary->code})" : '')) : null,
            'clinical_summary' => $summary !== '' ? $summary : null,
            'treatment_given' => $treatment !== '' ? $treatment : null,
        ];
    }

    /**
     * Issue a referral from an encounter.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Encounter $encounter, User $actor, array $data): Referral
    {
        return DB::transaction(function () use ($encounter, $actor, $data) {
            return Referral::create([
                'referral_number' => $this->nextNumber(),
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'referred_by' => $actor->id,
                'urgency' => $data['urgency'] ?? Priority::Normal->value,
                'destination_facility' => $data['destination_facility'],
                'destination_department' => $data['destination_department'] ?? null,
                'destination_contact' => $data['destination_contact'] ?? null,
                'reason' => $data['reason'],
                'diagnosis' => $data['diagnosis'] ?? null,
                'clinical_summary' => $data['clinical_summary'] ?? null,
                'treatment_given' => $data['treatment_given'] ?? null,
                'status' => ReferralStatus::Issued,
                'referred_at' => now(),
            ]);
        });
    }

    /**
     * Move a referral along its lifecycle, recording feedback from the
     * receiving facility where there is any.
     *
     * @throws ValidationException
     */
    public function setStatus(Referral $referral, ReferralStatus $status, ?string $feedback, User $actor): void
    {
        if (! $referral->status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "A referral marked {$referral->status->label()} cannot be changed to {$status->label()}.",
            ]);
        }

        $referral->update([
            'status' => $status,
            'feedback' => $feedback !== null && trim($feedback) !== '' ? trim($feedback) : $referral->feedback,
            'feedback_at' => $feedback !== null && trim($feedback) !== '' ? now() : $referral->feedback_at,
            'closed_by' => $status->isOpen() ? $referral->closed_by : $actor->id,
        ]);
    }

    /**
     * The referral letter as a PDF, with a counter-referral slip for the
     * receiving facility to return.
     */
    public function letter(Referral $referral): string
    {
        $referral->load(['patient', 'encounter', 'referredBy:id,name']);
        $patient = $referral->patient;
        $profile = $this->facility->profile();

        $vitals = ObservationSet::query()
            ->where('patient_id', $patient->id)
            ->with('observations')
            ->latest('recorded_at')
            ->first();

        $html = view('referrals.letter', [
            'facility' => [
                'name' => $profile['name'] ?? config('app.name'),
                'location' => trim(implode(', ', array_filter([$profile['lga'] ?? null, $profile['state'] ?? null]))),
                'code' => $profile['code'] ?? null,
            ],
            'referral' => [
                'number' => $referral->referral_number,
                'date' => $referral->referred_at->isoFormat('D MMMM YYYY'),
                'urgency' => $referral->urgency->label(),
                'urgency_class' => match ($referral->urgency) {
                    Priority::Emergency => 'badge-emergency',
                    Priority::Urgent => 'badge-urgent',
                    Priority::Normal => 'badge-routine',
                },
                'destination_facility' => $referral->destination_facility,
                'destination_department' => $referral->destination_department,
                'destination_contact' => $referral->destination_contact,
                'reason' => $referral->reason,
                'diagnosis' => $referral->diagnosis,
                'clinical_summary' => $referral->clinical_summary,
                'treatment_given' => $referral->treatment_given,
                'referred_by' => $referral->referredBy?->name,
                'encounter_date' => $referral->encounter?->started_at?->isoFormat('D MMM YYYY'),
            ],
            'patient' => [
                'name' => $patient->fullName(),
                'file_number' => $patient->file_number,
                'sex' => PatientOptions::SEXES[$patient->sex] ?? $patient->sex,
                'age' => $patient->age() !== null ? $patient->age().' years' : null,
                'dob' => $patient->date_of_birth?->isoFormat('D MMM YYYY'),
                'phone' => $patient->phone,
                'address' => trim(implode(', ', array_filter([$patient->address, $patient->lga, $patient->state]))),
                'next_of_kin' => trim(implode(' · ', array_filter([$patient->next_of_kin_name, $patient->next_of_kin_relationship, $patient->next_of_kin_phone]))),
                'coverage' => trim(implode(' · ', array_filter([$patient->coverage, $patient->hmo_name, $patient->hmo_number]))),
            ],
            'vitals' => $vitals ? [
                'recorded_at' => $vitals->recorded_at->isoFormat('D MMM YYYY, HH:mm'),
                'readings' => $vitals->observations
                    ->map(fn (Observation $o) => ['label' => $o->code->shortLabel(), 'value' => $o->display()])
                    ->values()
                    ->all(),
            ] : null,
            'allergies' => Allergy::query()
                ->where('patient_id', $patient->id)
                ->where('status', Allergy::STATUS_ACTIVE)
                ->get()
                ->map(fn (Allergy $a) => trim($a->substance.($a->reaction ? " ({$a->reaction})" : '')))
                ->all(),
            'problems' => Problem::query()
                ->where('patient_id', $patient->id)
                ->open()
                ->get()
                ->map(fn (Problem $p) => trim($p->name.($p->code ? " ({$p->code})" : '')))
                ->all(),
            'generated_at' => now()->isoFormat('D MMM YYYY, HH:mm'),
        ])->render();

        $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $referral->update(['printed_at' => now()]);

        return $dompdf->output();
    }

    /**
     * The next referral number, REF/YYYY/000001, sequential within the year.
     */
    private function nextNumber(): string
    {
        $year = now()->year;

        $last = Referral::query()
            ->where('referral_number', 'like', "REF/{$year}/%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('referral_number');

        $sequence = $last ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('REF/%d/%06d', $year, $sequence);
    }
}
