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
 * A folder with no way to reach the patient: no phone and no address. A
 * critical result, a missed appointment and a contact investigation all end
 * here.
 */
class PatientContactMissing extends DataQualityCheck
{
    public static function key(): string
    {
        return 'patient_contact_missing';
    }

    public static function name(): string
    {
        return 'No way to contact the patient';
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
        return 'A folder carrying neither a phone number nor an address, so nobody can be reached about a critical result, a missed appointment or a contact investigation.';
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
            ->where(fn (Builder $q) => $q->whereNull('phone')->orWhere('phone', ''))
            ->where(fn (Builder $q) => $q->whereNull('address')->orWhere('address', ''))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Patient || $subject->merged_into_id !== null) {
            return null;
        }

        if (filled($subject->phone) || filled($subject->address)) {
            return null;
        }

        return new Finding('No phone number and no address on the folder.');
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
