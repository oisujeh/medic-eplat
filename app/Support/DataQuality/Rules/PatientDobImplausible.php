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
 * A date of birth that cannot be right: in the future, or older than anyone
 * lives. Usually a mistyped year, and it distorts every age band it lands in.
 */
class PatientDobImplausible extends DataQualityCheck
{
    public static function key(): string
    {
        return 'patient_dob_implausible';
    }

    public static function name(): string
    {
        return 'Date of birth implausible';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Validity;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Major;
    }

    public static function description(): string
    {
        return 'A date of birth in the future, or one that would make the patient older than the age limit below. Almost always a mistyped year, and it distorts every age band the folder falls into.';
    }

    public static function subjectType(): string
    {
        return Patient::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'records-officer';
    }

    public static function defaultParameters(): array
    {
        return ['max_age' => 120];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'max_age', 'label' => 'Oldest plausible age', 'type' => 'number', 'suffix' => 'years', 'min' => 80, 'max' => 130],
        ];
    }

    public function candidates(array $parameters): iterable
    {
        $earliest = now()->subYears((int) $parameters['max_age'])->toDateString();

        return Patient::query()
            ->whereNull('merged_into_id')
            ->whereNotNull('date_of_birth')
            ->where(fn (Builder $q) => $q->where('date_of_birth', '>', now()->toDateString())->orWhere('date_of_birth', '<', $earliest))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Patient || $subject->merged_into_id !== null || $subject->date_of_birth === null) {
            return null;
        }

        $maxAge = (int) $parameters['max_age'];
        $dateOfBirth = $subject->date_of_birth;

        if ($dateOfBirth->isFuture()) {
            return new Finding(
                'Date of birth is in the future ('.$dateOfBirth->isoFormat('D MMM YYYY').').',
                ['date_of_birth' => $dateOfBirth->toDateString()],
            );
        }

        $age = (int) $dateOfBirth->diffInYears(now());

        if ($age <= $maxAge) {
            return null;
        }

        return new Finding(
            "Date of birth makes the patient {$age} years old.",
            ['date_of_birth' => $dateOfBirth->toDateString(), 'age' => $age, 'max_age' => $maxAge],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Patient ? route('patients.edit', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return Patient::query()->whereNull('merged_into_id')->whereNotNull('date_of_birth')->count();
    }
}
