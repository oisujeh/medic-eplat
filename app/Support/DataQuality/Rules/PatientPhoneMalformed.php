<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Patient;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use App\Support\DataQuality\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * A phone number that is not a phone number. It reads as a contact on the
 * folder until somebody tries to use it.
 */
class PatientPhoneMalformed extends DataQualityCheck
{
    public static function key(): string
    {
        return 'patient_phone_malformed';
    }

    public static function name(): string
    {
        return 'Phone number malformed';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Validity;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Minor;
    }

    public static function description(): string
    {
        return 'A phone number that does not fit the pattern below once spaces, dashes and brackets are taken out. It looks like a contact on the folder until somebody tries to use it.';
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
        // Nigerian mobile and landline numbers: 0 followed by ten digits, or
        // the same number in +234 form.
        return ['pattern' => '^(?:\+?234|0)\d{10}$'];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'pattern', 'label' => 'Accepted pattern (regular expression)', 'type' => 'text'],
        ];
    }

    public function candidates(array $parameters): iterable
    {
        return Patient::query()
            ->whereNull('merged_into_id')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Patient || $subject->merged_into_id !== null || blank($subject->phone)) {
            return null;
        }

        if (PhoneNumber::matches($subject->phone, (string) $parameters['pattern'])) {
            return null;
        }

        return new Finding(
            "Phone number \"{$subject->phone}\" is not a usable number.",
            ['phone' => $subject->phone],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Patient ? route('patients.edit', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return Patient::query()
            ->whereNull('merged_into_id')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();
    }
}
