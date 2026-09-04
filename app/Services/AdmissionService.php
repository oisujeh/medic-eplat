<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\BedStatus;
use App\Enums\DischargeType;
use App\Models\Admission;
use App\Models\AdmissionNote;
use App\Models\Bed;
use App\Models\BillCharge;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\ServiceCharge;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The inpatient lifecycle: ordering an admission, placing the patient in a
 * bed, moving them between beds and discharging them. Bed statuses and the
 * running bill are kept in step here so no controller has to.
 */
class AdmissionService
{
    public function __construct(
        private readonly PatientFlowService $flow,
        private readonly BillingService $billing,
    ) {}

    /**
     * Order an admission. The patient waits for a bed until one is assigned.
     *
     * @throws ValidationException when the patient is already an inpatient
     */
    public function request(
        Patient $patient,
        User $actor,
        string $diagnosis,
        ?string $reason = null,
        ?Ward $ward = null,
        ?User $attending = null,
        ?Encounter $encounter = null,
    ): Admission {
        if ($patient->activeAdmission()) {
            throw ValidationException::withMessages([
                'patient_id' => "{$patient->fullName()} already has an active admission.",
            ]);
        }

        return DB::transaction(function () use ($patient, $actor, $diagnosis, $reason, $ward, $attending, $encounter) {
            $visit = $encounter?->visit ?? $this->flow->ensureOpenVisit($patient, $actor, 'Admission');

            $admission = Admission::create([
                'admission_number' => 'TMP-'.$patient->id.'-'.now()->timestamp,
                'patient_id' => $patient->id,
                'visit_id' => $visit->id,
                'encounter_id' => $encounter?->id,
                'ward_id' => $ward?->id,
                'status' => AdmissionStatus::Pending,
                'admitting_diagnosis' => $diagnosis,
                'reason' => $reason,
                'requested_by' => $actor->id,
                'attending_id' => $attending?->id,
            ]);

            $admission->update([
                'admission_number' => sprintf('ADM/%d/%06d', now()->year, $admission->id),
            ]);

            return $admission;
        });
    }

    /**
     * Order an admission from a consultation whose outcome was "admit". Does
     * nothing when the patient is already an inpatient.
     */
    public function requestFromEncounter(Encounter $encounter, User $actor): ?Admission
    {
        $patient = $encounter->patient;

        if ($patient->activeAdmission()) {
            return null;
        }

        return $this->request(
            patient: $patient,
            actor: $actor,
            diagnosis: $encounter->diagnosisSummary() ?: 'See consultation notes',
            reason: 'Admitted from consultation',
            attending: $actor,
            encounter: $encounter,
        );
    }

    /**
     * Place a waiting patient in a bed. Posts the admission fee from the fee
     * schedule to the visit's bill.
     *
     * @throws ValidationException when the bed cannot take the patient
     */
    public function admit(Admission $admission, Bed $bed, User $actor, ?User $attending = null): Admission
    {
        $this->assertStatus($admission, AdmissionStatus::Pending, 'This admission is not awaiting a bed.');
        $this->assertBedFree($bed);

        return DB::transaction(function () use ($admission, $bed, $actor, $attending) {
            $bed->update(['status' => BedStatus::Occupied]);

            $admission->update([
                'ward_id' => $bed->ward_id,
                'bed_id' => $bed->id,
                'status' => AdmissionStatus::Admitted,
                'admitted_by' => $actor->id,
                'admitted_at' => now(),
                'attending_id' => $attending?->id ?? $admission->attending_id,
            ]);

            $admission->movements()->create([
                'to_ward_id' => $bed->ward_id,
                'to_bed_id' => $bed->id,
                'moved_by' => $actor->id,
                'moved_at' => now(),
            ]);

            $fee = ServiceCharge::query()->where('code', ServiceCharge::ADMISSION)->active()->first();

            if ($fee) {
                $this->billing->postCharge(
                    bill: $this->billing->openBillFor($admission->patient, $admission->visit),
                    source: BillCharge::SOURCE_ADMISSION,
                    description: "Admission — {$bed->ward->name}",
                    quantity: 1,
                    unitPrice: (float) $fee->price,
                    actor: $actor,
                    reference: $admission,
                );
            }

            return $admission->refresh();
        });
    }

    /**
     * Move an inpatient to another bed, in the same ward or a different one.
     *
     * @throws ValidationException when the bed cannot take the patient
     */
    public function transfer(Admission $admission, Bed $to, User $actor, ?string $reason = null): Admission
    {
        $this->assertStatus($admission, AdmissionStatus::Admitted, 'Only an admitted patient can be transferred.');

        if ($to->id === $admission->bed_id) {
            throw ValidationException::withMessages(['bed_id' => 'The patient is already in that bed.']);
        }

        $this->assertBedFree($to);

        return DB::transaction(function () use ($admission, $to, $actor, $reason) {
            $admission->bed?->update(['status' => BedStatus::Available]);
            $to->update(['status' => BedStatus::Occupied]);

            $admission->movements()->create([
                'from_ward_id' => $admission->ward_id,
                'from_bed_id' => $admission->bed_id,
                'to_ward_id' => $to->ward_id,
                'to_bed_id' => $to->id,
                'reason' => $reason,
                'moved_by' => $actor->id,
                'moved_at' => now(),
            ]);

            $admission->update(['ward_id' => $to->ward_id, 'bed_id' => $to->id]);

            return $admission->refresh();
        });
    }

    /**
     * End the inpatient episode. Frees the bed and bills the stay at the
     * ward's daily bed rate, counting the admission and discharge days.
     */
    public function discharge(
        Admission $admission,
        User $actor,
        DischargeType $type,
        ?string $summary = null,
        ?Carbon $followUp = null,
    ): Admission {
        $this->assertStatus($admission, AdmissionStatus::Admitted, 'Only an admitted patient can be discharged.');

        return DB::transaction(function () use ($admission, $actor, $type, $summary, $followUp) {
            $admission->bed?->update(['status' => BedStatus::Available]);

            $admission->update([
                'status' => AdmissionStatus::Discharged,
                'discharged_by' => $actor->id,
                'discharged_at' => now(),
                'discharge_type' => $type,
                'discharge_summary' => $summary,
                'follow_up_at' => $followUp,
            ]);

            $this->postBedCharges($admission->refresh(), $actor);

            return $admission;
        });
    }

    /**
     * Withdraw an admission order that never reached a bed.
     */
    public function cancel(Admission $admission, User $actor, ?string $reason = null): Admission
    {
        $this->assertStatus($admission, AdmissionStatus::Pending, 'Only an admission awaiting a bed can be cancelled.');

        $admission->update([
            'status' => AdmissionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $admission;
    }

    /**
     * Add a ward-round, progress or nursing note.
     */
    public function addNote(Admission $admission, User $author, string $type, string $note): AdmissionNote
    {
        if (! $admission->isActive()) {
            throw ValidationException::withMessages(['note' => 'Notes can only be added to an active admission.']);
        }

        return $admission->notes()->create([
            'patient_id' => $admission->patient_id,
            'author_id' => $author->id,
            'type' => $type,
            'note' => $note,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Bill the stay at the ward's daily bed rate, if the ward has one.
     */
    private function postBedCharges(Admission $admission, User $actor): void
    {
        $charge = $admission->ward?->bedCharge;
        $days = $admission->lengthOfStayDays();

        if (! $charge || ! $charge->is_active || ! $days) {
            return;
        }

        $this->billing->postCharge(
            bill: $this->billing->openBillFor($admission->patient, $admission->visit),
            source: BillCharge::SOURCE_ADMISSION,
            description: "{$charge->name} — {$admission->ward->name}, {$days} ".($days === 1 ? 'day' : 'days'),
            quantity: $days,
            unitPrice: (float) $charge->price,
            actor: $actor,
            reference: $admission,
        );
    }

    private function assertStatus(Admission $admission, AdmissionStatus $expected, string $message): void
    {
        if ($admission->status !== $expected) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function assertBedFree(Bed $bed): void
    {
        $bed->loadMissing('ward');

        if (! $bed->isAvailable() || ! $bed->ward->is_active) {
            throw ValidationException::withMessages([
                'bed_id' => "{$bed->label} in {$bed->ward->name} is not available.",
            ]);
        }
    }
}
