<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Observation;
use App\Models\ObservationSet;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A reading outside the sanity bounds the observation registry sets. Entry
 * refuses these, so one here came in another way — an import, a correction
 * made against the database, or a bound tightened since. Left alone it drags
 * every average it is part of.
 */
class ObservationImplausible extends DataQualityCheck
{
    public static function key(): string
    {
        return 'observation_implausible';
    }

    public static function name(): string
    {
        return 'Implausible reading';
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
        return 'A set of readings holding a value outside the sanity bounds of the observation registry, such as a temperature of 400. It drags every average it is part of.';
    }

    public static function subjectType(): string
    {
        return ObservationSet::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'nurse';
    }

    public function candidates(array $parameters): iterable
    {
        return ObservationSet::query()->with('observations')->lazyById(200);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof ObservationSet) {
            return null;
        }

        $subject->loadMissing('observations');

        $outside = $subject->observations
            ->filter(function (Observation $observation): bool {
                $bounds = $observation->code->bounds();

                return $observation->value !== null
                    && $bounds !== null
                    && ($observation->value < $bounds[0] || $observation->value > $bounds[1]);
            })
            ->map(fn (Observation $observation) => [
                'code' => $observation->code->value,
                'label' => $observation->code->label(),
                'value' => $observation->value,
                'unit' => $observation->unit,
            ])
            ->values();

        if ($outside->isEmpty()) {
            return null;
        }

        $names = $outside->pluck('label')->implode(', ');

        return new Finding(
            'Reading outside its plausible range: '.$names.'.',
            ['readings' => $outside->all()],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof ObservationSet ? route('patients.show', $subject->patient_id) : null;
    }
}
