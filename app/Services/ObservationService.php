<?php

namespace App\Services;

use App\Enums\AlertLevel;
use App\Enums\ObservationCode;
use App\Models\Admission;
use App\Models\Encounter;
use App\Models\ObservationSet;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\Visit;
use App\Support\ObservationInterpreter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The one place measurements are written. Derives BMI, interprets each
 * reading against the patient's age and stores the set with its worst flag.
 */
class ObservationService
{
    /**
     * Record a set of readings. `$values` is keyed by ObservationCode value;
     * blank entries are ignored. The context (queue entry, encounter or
     * admission) fills in the visit and the other links it implies.
     *
     * @param  array<string, mixed>  $values
     */
    public function record(
        Patient $patient,
        User $actor,
        array $values,
        ?string $notes = null,
        ?QueueEntry $queueEntry = null,
        ?Encounter $encounter = null,
        ?Admission $admission = null,
        ?CarbonInterface $recordedAt = null,
    ): ObservationSet {
        $readings = $this->normalise($values);
        $recordedAt ??= now();
        $age = $patient->age();

        if ($queueEntry && ! $encounter) {
            $encounter = Encounter::where('queue_entry_id', $queueEntry->id)->first();
        }

        $visit = $encounter?->visit ?? $queueEntry?->visit ?? $admission?->visit;
        $queueEntry ??= $encounter?->queueEntry;
        $admission ??= $encounter?->admission;

        return DB::transaction(function () use ($patient, $actor, $readings, $notes, $visit, $queueEntry, $encounter, $admission, $recordedAt, $age) {
            $set = ObservationSet::create([
                'patient_id' => $patient->id,
                'visit_id' => $visit?->id,
                'encounter_id' => $encounter?->id,
                'queue_entry_id' => $queueEntry?->id,
                'admission_id' => $admission?->id,
                'recorded_by' => $actor->id,
                'notes' => $notes,
                'recorded_at' => $recordedAt,
                'alert_level' => AlertLevel::Normal,
            ]);

            $worst = AlertLevel::Normal;

            foreach ($readings as $value => $reading) {
                $code = ObservationCode::from($value);
                $interpretation = $code->isText() ? null : ObservationInterpreter::interpret($code, (float) $reading, $age);
                $level = $interpretation['level'] ?? null;
                $worst = $level ? $worst->max($level) : $worst;

                $set->observations()->create([
                    'patient_id' => $patient->id,
                    'code' => $code,
                    'value' => $code->isText() ? null : $reading,
                    'text_value' => $code->isText() ? $reading : null,
                    'unit' => $code->unit(),
                    'level' => $level,
                    'flag' => $interpretation['flag'] ?? null,
                    'recorded_at' => $recordedAt,
                ]);
            }

            $set->update(['alert_level' => $worst]);

            return $set->load('observations', 'recordedBy:id,name');
        });
    }

    /**
     * Keep the readings that were entered, keyed by code, adding BMI when
     * weight and height are both present.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed> keyed by ObservationCode value
     */
    private function normalise(array $values): array
    {
        $readings = [];

        foreach (ObservationCode::enterable() as $code) {
            $value = $values[$code->value] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $readings[$code->value] = $code->isText() ? trim((string) $value) : (float) $value;
        }

        $bmi = ObservationInterpreter::bmi(
            isset($readings[ObservationCode::Weight->value]) ? (float) $readings[ObservationCode::Weight->value] : null,
            isset($readings[ObservationCode::Height->value]) ? (float) $readings[ObservationCode::Height->value] : null,
        );

        if ($bmi !== null) {
            $readings[ObservationCode::Bmi->value] = $bmi;
        }

        return $readings;
    }
}
