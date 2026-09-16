<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Enums\EncounterStatus;
use App\Models\Encounter;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A consultation signed off with nothing on the problem list. Whatever the
 * patient was seen for, the morbidity return will not carry it.
 */
class EncounterMissingDiagnosis extends DataQualityCheck
{
    public static function key(): string
    {
        return 'encounter_missing_diagnosis';
    }

    public static function name(): string
    {
        return 'Signed encounter without a diagnosis';
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
        return 'An encounter signed with nothing on the problem list. Whatever the patient was seen for, no morbidity return will carry it.';
    }

    public static function subjectType(): string
    {
        return Encounter::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'physician';
    }

    public function candidates(array $parameters): iterable
    {
        return Encounter::query()
            ->where('status', EncounterStatus::Signed)
            ->whereDoesntHave('problems')
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Encounter || $subject->status !== EncounterStatus::Signed) {
            return null;
        }

        if ($subject->problems()->exists()) {
            return null;
        }

        return new Finding('Encounter was signed with nothing on the problem list.');
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Encounter ? route('encounters.show', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return Encounter::query()->where('status', EncounterStatus::Signed)->count();
    }
}
