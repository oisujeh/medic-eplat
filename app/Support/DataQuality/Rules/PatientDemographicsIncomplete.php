<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Patient;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A folder that cannot say how old the patient is or where they live: age
 * bands and residence carry every return the facility sends out, and neither
 * can be reconstructed later from anything else in the record.
 */
class PatientDemographicsIncomplete extends DataQualityCheck
{
    public static function key(): string
    {
        return 'patient_demographics_incomplete';
    }

    public static function name(): string
    {
        return 'Patient demographics incomplete';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Completeness;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Major;
    }

    public static function description(): string
    {
        return 'A folder with no date of birth, not even an estimated one, or with no state and LGA of residence. Both carry the age bands and geography on every return the facility sends out.';
    }

    public static function subjectType(): string
    {
        return Patient::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'records-officer';
    }

    public function candidates(array $parameters): iterable
    {
        return Patient::query()
            ->whereNull('merged_into_id')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $dob) => $dob->whereNull('date_of_birth')->where('dob_estimated', false))
                ->orWhereNull('state')
                ->orWhereNull('lga'))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Patient || $subject->merged_into_id !== null) {
            return null;
        }

        $missing = [];

        if ($subject->date_of_birth === null && ! $subject->dob_estimated) {
            $missing[] = 'date of birth';
        }

        if (blank($subject->state)) {
            $missing[] = 'state';
        }

        if (blank($subject->lga)) {
            $missing[] = 'LGA';
        }

        if ($missing === []) {
            return null;
        }

        return new Finding(
            'Folder is missing '.implode(', ', $missing).'.',
            ['missing' => $missing],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Patient ? route('patients.edit', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return Patient::query()->whereNull('merged_into_id')->count();
    }
}
