<?php

namespace App\Services;

use App\Enums\IdentifierType;
use App\Enums\MatchCandidateStatus;
use App\Events\PatientsMerged;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientMatchCandidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Folds a duplicate patient record into the one that survives.
 *
 * Every clinical, financial and administrative record moves to the
 * survivor; blanks on the survivor are filled from the duplicate; the
 * duplicate's file number is kept as a previous identifier so an old card
 * still finds the right chart; and the duplicate stays as a tombstone that
 * points at the survivor. The audit trail records the merge once, with what
 * moved, rather than one entry per moved row.
 */
class PatientMerger
{
    /**
     * Tables whose patient_id column moves to the survivor.
     *
     * @var list<string>
     */
    private const PATIENT_TABLES = [
        'visits', 'queue_entries', 'encounters', 'observation_sets', 'observations',
        'patient_allergies', 'problems', 'medications', 'lab_results', 'patient_alerts',
        'lab_orders', 'bills', 'dispenses', 'appointments', 'immunizations',
        'admissions', 'admission_notes', 'claims', 'pregnancies', 'deliveries', 'births',
        'surveillance_cases', 'referrals', 'domain_events',
    ];

    /**
     * Survivor attributes filled from the duplicate when blank.
     *
     * @var list<string>
     */
    private const FILLABLE_BLANKS = [
        'title', 'other_names', 'date_of_birth', 'marital_status', 'nin', 'phone', 'email',
        'address', 'state', 'lga', 'ward', 'mother_patient_id', 'next_of_kin_name',
        'next_of_kin_relationship', 'next_of_kin_phone', 'hmo_number', 'hmo_plan', 'hmo_expires_at',
    ];

    public function __construct(
        private readonly AuditTrail $audit,
        private readonly DomainEvents $events,
    ) {}

    /**
     * @throws ValidationException
     */
    public function merge(Patient $survivor, Patient $duplicate, User $actor, ?string $reason = null): Patient
    {
        if ($survivor->is($duplicate)) {
            throw ValidationException::withMessages(['survivor_id' => 'Choose which of the two records to keep.']);
        }

        foreach ([$survivor, $duplicate] as $record) {
            if ($record->merged_into_id !== null) {
                throw ValidationException::withMessages(['survivor_id' => "{$record->file_number} has already been merged into another record."]);
            }
        }

        return DB::transaction(fn () => $this->audit->withReason($reason, function () use ($survivor, $duplicate, $actor, $reason) {
            $moved = $this->moveRecords($survivor, $duplicate);
            $filled = $this->fillBlanks($survivor, $duplicate);
            $this->moveIdentifiers($survivor, $duplicate);
            $this->settleQueue($survivor, $duplicate, $actor);

            $duplicate->mergedInto()->associate($survivor);
            $duplicate->merged_at = now();
            $duplicate->save();
            $duplicate->identifiers()->delete();

            $this->audit->record(
                AuditLog::ACTION_MERGED,
                $survivor,
                $survivor->id,
                ['merged_from' => $duplicate->file_number],
                ['moved' => $moved, 'filled' => $filled],
                "Merged {$duplicate->file_number} into {$survivor->file_number}",
                $reason,
            );

            $this->events->publish(new PatientsMerged($survivor, $duplicate, $moved, $actor));

            return $survivor->refresh();
        }));
    }

    /**
     * @return array<string, int> rows moved per table, only where any moved
     */
    private function moveRecords(Patient $survivor, Patient $duplicate): array
    {
        $moved = [];

        foreach (self::PATIENT_TABLES as $table) {
            $count = DB::table($table)->where('patient_id', $duplicate->id)->update(['patient_id' => $survivor->id]);

            if ($count > 0) {
                $moved[$table] = $count;
            }
        }

        $newborn = DB::table('births')->where('newborn_patient_id', $duplicate->id)->update(['newborn_patient_id' => $survivor->id]);
        $children = DB::table('patients')->where('mother_patient_id', $duplicate->id)->update(['mother_patient_id' => $survivor->id]);

        if ($newborn > 0) {
            $moved['births.newborn'] = $newborn;
        }

        if ($children > 0) {
            $moved['patients.mother'] = $children;
        }

        // One death per patient: the survivor's stands; the duplicate's moves
        // only when the survivor has none.
        $death = $duplicate->death;

        if ($death !== null) {
            if ($survivor->death()->exists()) {
                $death->delete();
            } else {
                $death->update(['patient_id' => $survivor->id]);
                $moved['deaths'] = 1;
            }
        }

        return $moved;
    }

    /**
     * @return list<string> the attributes filled
     */
    private function fillBlanks(Patient $survivor, Patient $duplicate): array
    {
        $filled = [];

        foreach (self::FILLABLE_BLANKS as $attribute) {
            if (blank($survivor->getAttribute($attribute)) && filled($duplicate->getAttribute($attribute))) {
                $survivor->setAttribute($attribute, $duplicate->getAttribute($attribute));
                $filled[] = $attribute;
            }
        }

        if ($survivor->isDirty()) {
            $survivor->save();
        }

        return $filled;
    }

    private function moveIdentifiers(Patient $survivor, Patient $duplicate): void
    {
        $keep = function (IdentifierType $type, string $value, ?string $issuer = null) use ($survivor): void {
            $survivor->identifiers()->firstOrCreate(
                ['type' => $type->value, 'value' => $value],
                ['issuer' => $issuer],
            );
        };

        // The old card keeps working.
        $keep(IdentifierType::PreviousFileNumber, $duplicate->file_number, 'Merged record');

        foreach ($duplicate->identifiers as $identifier) {
            if ($identifier->type === IdentifierType::FileNumber) {
                continue;
            }

            // A unique identifier the survivor already holds a different value
            // for is a conflict the merge note records; it is not copied.
            if ($identifier->type->isUnique() && $survivor->identifiers()->where('type', $identifier->type->value)->exists()) {
                continue;
            }

            $keep($identifier->type, $identifier->value, $identifier->issuer);
        }
    }

    private function settleQueue(Patient $survivor, Patient $duplicate, User $actor): void
    {
        PatientMatchCandidate::query()
            ->involving($duplicate)
            ->pending()
            ->get()
            ->each(fn (PatientMatchCandidate $candidate) => $candidate->update([
                'status' => MatchCandidateStatus::Merged,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'notes' => "Merged into {$survivor->file_number}",
            ]));
    }
}
