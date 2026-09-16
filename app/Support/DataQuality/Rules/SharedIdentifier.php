<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Patient;
use App\Models\PatientIdentifier;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use App\Support\DataQuality\SharedIdentifiers;
use Illuminate\Database\Eloquent\Model;

/**
 * The same national, health or HMO number on two folders. Either it is one
 * patient registered twice, or one of the numbers belongs to somebody else —
 * both are worth a person's attention.
 */
class SharedIdentifier extends DataQualityCheck
{
    public static function key(): string
    {
        return 'shared_identifier';
    }

    public static function name(): string
    {
        return 'Identifier shared between folders';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Duplicate;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Critical;
    }

    public static function description(): string
    {
        return 'The same identifier — a NIN, national health ID or HMO number — recorded against more than one folder. Either it is one patient registered twice, or one of the numbers belongs to somebody else.';
    }

    public static function subjectType(): string
    {
        return PatientIdentifier::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'records-officer';
    }

    public function candidates(array $parameters): iterable
    {
        return SharedIdentifiers::shared();
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof PatientIdentifier) {
            return null;
        }

        $others = PatientIdentifier::query()
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->where('type', $subject->type)
            ->where('value', $subject->value)
            ->where('patient_id', '!=', $subject->patient_id)
            ->whereHas('patient', fn ($q) => $q->whereNull('merged_into_id'))
            ->get();

        if ($others->isEmpty()) {
            return null;
        }

        $folders = $others
            ->map(fn (PatientIdentifier $identifier) => $identifier->patient?->file_number)
            ->filter()
            ->values();

        return new Finding(
            $subject->type->label()." {$subject->value} is also on ".$folders->implode(', ').'.',
            ['type' => $subject->type->value, 'value' => $subject->value, 'other_folders' => $folders->all()],
        );
    }

    public function href(Model $subject): ?string
    {
        if (! $subject instanceof PatientIdentifier) {
            return null;
        }

        $patient = $subject->patient;

        return $patient instanceof Patient ? route('patients.edit', $patient) : null;
    }
}
