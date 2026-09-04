<?php

namespace App\Services;

use App\Enums\PregnancyStatus;
use App\Models\Birth;
use App\Models\Delivery;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The maternity workflow: booking a pregnancy, recording its delivery and
 * the babies born, and registering a newborn as a patient.
 */
class MaternityService
{
    /**
     * Open a pregnancy episode for a woman.
     *
     * @param  array{lmp?: string|null, edd?: string|null, gravida?: int|null, para?: int|null, booking_date?: string|null, risk_factors?: array<int, string>|null, notes?: string|null}  $data
     *
     * @throws ValidationException when the patient cannot be booked
     */
    public function book(Patient $patient, User $actor, array $data): Pregnancy
    {
        if ($patient->sex !== 'F') {
            throw ValidationException::withMessages(['patient_id' => 'Only a female patient can be booked for antenatal care.']);
        }

        if ($patient->activePregnancy()) {
            throw ValidationException::withMessages(['patient_id' => "{$patient->fullName()} already has an active pregnancy on record."]);
        }

        return DB::transaction(function () use ($patient, $actor, $data) {
            $pregnancy = Pregnancy::create([
                'pregnancy_number' => 'TMP-'.$patient->id.'-'.now()->timestamp,
                'patient_id' => $patient->id,
                'status' => PregnancyStatus::Active,
                ...$this->datedAttributes($data),
                'booking_date' => $data['booking_date'] ?? now()->toDateString(),
                'booked_by' => $actor->id,
            ]);

            $pregnancy->update(['pregnancy_number' => sprintf('PRG/%d/%06d', now()->year, $pregnancy->id)]);

            return $pregnancy->refresh();
        });
    }

    /**
     * Revise the booking details of an active pregnancy.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Pregnancy $pregnancy, array $data): Pregnancy
    {
        $this->assertActive($pregnancy);

        $pregnancy->update([
            ...$this->datedAttributes($data),
            'booking_date' => $data['booking_date'] ?? $pregnancy->booking_date,
        ]);

        return $pregnancy->refresh();
    }

    /**
     * Record the delivery and every baby born, closing the pregnancy. The
     * delivery is linked to the mother's current admission when she is on a
     * ward.
     *
     * @param  array<string, mixed>  $delivery
     * @param  array<int, array<string, mixed>>  $births
     */
    public function recordDelivery(Pregnancy $pregnancy, User $actor, array $delivery, array $births): Delivery
    {
        $this->assertActive($pregnancy);

        if ($births === []) {
            throw ValidationException::withMessages(['births' => 'Record at least one baby.']);
        }

        return DB::transaction(function () use ($pregnancy, $actor, $delivery, $births) {
            $record = Delivery::create([
                'pregnancy_id' => $pregnancy->id,
                'patient_id' => $pregnancy->patient_id,
                'admission_id' => $pregnancy->patient->activeAdmission()?->id,
                'delivered_at' => Carbon::parse($delivery['delivered_at']),
                'mode' => $delivery['mode'],
                'labour_onset' => $delivery['labour_onset'] ?? null,
                'gestational_age_weeks' => $delivery['gestational_age_weeks'] ?? $pregnancy->gestationalAgeWeeks(Carbon::parse($delivery['delivered_at'])),
                'place' => $delivery['place'] ?? Delivery::PLACE_FACILITY,
                'attendant_id' => $delivery['attendant_id'] ?? null,
                'complications' => array_values(array_filter($delivery['complications'] ?? [])),
                'blood_loss_ml' => $delivery['blood_loss_ml'] ?? null,
                'maternal_outcome' => $delivery['maternal_outcome'],
                'notes' => $delivery['notes'] ?? null,
                'recorded_by' => $actor->id,
            ]);

            foreach (array_values($births) as $index => $baby) {
                $record->births()->create([
                    'patient_id' => $pregnancy->patient_id,
                    'birth_order' => $index + 1,
                    'outcome' => $baby['outcome'],
                    'sex' => $baby['sex'],
                    'weight_grams' => $baby['weight_grams'] ?? null,
                    'apgar_1' => $baby['apgar_1'] ?? null,
                    'apgar_5' => $baby['apgar_5'] ?? null,
                    'resuscitated' => (bool) ($baby['resuscitated'] ?? false),
                    'breastfed_within_hour' => (bool) ($baby['breastfed_within_hour'] ?? false),
                    'bcg_given' => (bool) ($baby['bcg_given'] ?? false),
                    'opv0_given' => (bool) ($baby['opv0_given'] ?? false),
                    'hepb0_given' => (bool) ($baby['hepb0_given'] ?? false),
                    'condition' => $baby['condition'] ?? null,
                    'notes' => $baby['notes'] ?? null,
                ]);
            }

            $pregnancy->update([
                'status' => PregnancyStatus::Delivered,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ]);

            return $record->refresh();
        });
    }

    /**
     * Close a pregnancy that ended without a delivery.
     */
    public function closeAsLoss(Pregnancy $pregnancy, User $actor, string $note): Pregnancy
    {
        $this->assertActive($pregnancy);

        $pregnancy->update([
            'status' => PregnancyStatus::Lost,
            'outcome_note' => $note,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ]);

        return $pregnancy;
    }

    /**
     * Open a patient record for a live-born baby, carrying the mother's
     * contact details, and link it to the birth.
     */
    public function registerNewborn(Birth $birth, User $actor): Patient
    {
        if (! $birth->outcome->isLive()) {
            throw ValidationException::withMessages(['birth' => 'Only a live-born baby can be registered as a patient.']);
        }

        if ($birth->newborn_patient_id) {
            throw ValidationException::withMessages(['birth' => 'This baby already has a patient record.']);
        }

        return DB::transaction(function () use ($birth, $actor) {
            $mother = $birth->mother;
            $delivery = $birth->delivery;

            $baby = new Patient;
            $baby->fill([
                'surname' => $mother->surname,
                'first_name' => 'Baby of '.$mother->first_name,
                'date_of_birth' => $delivery->delivered_at->toDateString(),
                'sex' => $birth->sex,
                'phone' => $mother->phone,
                'address' => $mother->address,
                'nationality' => $mother->nationality ?? 'Nigerian',
                'state' => $mother->state,
                'lga' => $mother->lga,
                'next_of_kin_name' => $mother->fullName(),
                'next_of_kin_relationship' => 'Parent',
                'next_of_kin_phone' => $mother->phone,
                'coverage' => 'private',
                'is_transfer' => false,
                'visit_category' => 'Outpatient',
                'outpatient_service' => 'Child Follow-up & Immunization',
            ]);
            $baby->registered_by = $actor->id;
            $baby->file_number = 'TMP-'.Str::uuid();
            $baby->save();

            $baby->file_number = sprintf('MEP/%d/%06d', $baby->created_at->year, $baby->id);
            $baby->save();

            $birth->update(['newborn_patient_id' => $baby->id]);

            return $baby;
        });
    }

    /**
     * The LMP / EDD pair, deriving the EDD from the LMP when not given.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function datedAttributes(array $data): array
    {
        $lmp = ! empty($data['lmp']) ? Carbon::parse($data['lmp']) : null;
        $edd = ! empty($data['edd']) ? Carbon::parse($data['edd']) : ($lmp ? Pregnancy::eddFromLmp($lmp) : null);

        return [
            'lmp' => $lmp?->toDateString(),
            'edd' => $edd?->toDateString(),
            'gravida' => $data['gravida'] ?? null,
            'para' => $data['para'] ?? null,
            'risk_factors' => array_values(array_filter($data['risk_factors'] ?? [])),
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function assertActive(Pregnancy $pregnancy): void
    {
        if (! $pregnancy->isActive()) {
            throw ValidationException::withMessages(['status' => 'This pregnancy has already been closed.']);
        }
    }
}
