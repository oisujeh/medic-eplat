<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Problem;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A diagnosis written in words with no ICD-10 code behind it. It reads fine
 * on the chart and is invisible to the morbidity return, to surveillance
 * screening and to any count of the condition.
 */
class DiagnosisNotCoded extends DataQualityCheck
{
    public static function key(): string
    {
        return 'diagnosis_not_coded';
    }

    public static function name(): string
    {
        return 'Diagnosis not coded';
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
        return 'A diagnosis recorded as free text with no ICD-10 code. It reads fine on the chart and is invisible to the morbidity return, to surveillance screening and to any count of the condition.';
    }

    public static function subjectType(): string
    {
        return Problem::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'physician';
    }

    public function candidates(array $parameters): iterable
    {
        return Problem::query()
            ->where(fn (Builder $q) => $q->whereNull('code')->orWhere('code', ''))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Problem || filled($subject->code)) {
            return null;
        }

        return new Finding(
            "\"{$subject->name}\" was recorded without an ICD-10 code.",
            ['diagnosis' => $subject->name],
        );
    }

    public function href(Model $subject): ?string
    {
        if (! $subject instanceof Problem) {
            return null;
        }

        return $subject->encounter_id === null
            ? route('patients.show', $subject->patient_id)
            : route('encounters.show', $subject->encounter_id);
    }
}
