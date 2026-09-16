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
 * No next of kin on the folder. It is what consent, admission and a death in
 * the facility all fall back on.
 */
class PatientNextOfKinMissing extends DataQualityCheck
{
    public static function key(): string
    {
        return 'patient_next_of_kin_missing';
    }

    public static function name(): string
    {
        return 'Next of kin missing';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Completeness;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Minor;
    }

    public static function description(): string
    {
        return 'A folder with no next-of-kin name or no number to reach them on. Consent, admission and a death in the facility all fall back on it.';
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
                ->whereNull('next_of_kin_name')->orWhere('next_of_kin_name', '')
                ->orWhereNull('next_of_kin_phone')->orWhere('next_of_kin_phone', ''))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Patient || $subject->merged_into_id !== null) {
            return null;
        }

        if (filled($subject->next_of_kin_name) && filled($subject->next_of_kin_phone)) {
            return null;
        }

        return new Finding(blank($subject->next_of_kin_name)
            ? 'No next of kin recorded.'
            : 'Next of kin recorded with no phone number.');
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
