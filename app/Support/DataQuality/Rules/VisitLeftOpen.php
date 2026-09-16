<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Elapsed;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A visit nobody closed. The patient shows as still in the facility, the
 * attendance count for the day never settles, and the next visit opens on
 * top of it.
 */
class VisitLeftOpen extends DataQualityCheck
{
    public static function key(): string
    {
        return 'visit_left_open';
    }

    public static function name(): string
    {
        return 'Visit left open';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Timeliness;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Minor;
    }

    public static function description(): string
    {
        return 'A visit still open after the time below, so the patient reads as being in the facility and the day\'s attendance never settles.';
    }

    public static function subjectType(): string
    {
        return Visit::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'records-officer';
    }

    public static function defaultParameters(): array
    {
        return ['hours' => 24];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'hours', 'label' => 'Allowed before closing', 'type' => 'number', 'suffix' => 'hours', 'min' => 4, 'max' => 336],
        ];
    }

    public static function runsOnWrite(): bool
    {
        return false;
    }

    public function candidates(array $parameters): iterable
    {
        return Visit::query()
            ->where('status', VisitStatus::Open)
            ->where('opened_at', '<=', now()->subHours((int) $parameters['hours']))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Visit || $subject->status !== VisitStatus::Open) {
            return null;
        }

        $opened = $subject->opened_at;
        $hours = (int) $parameters['hours'];

        if ($opened === null || $opened->diffInHours(now()) < $hours) {
            return null;
        }

        return new Finding(
            'Visit '.$subject->visit_number.' has been open for '.Elapsed::since($opened).'.',
            ['visit_number' => $subject->visit_number, 'opened_at' => $opened->toIso8601String()],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Visit ? route('patients.show', $subject->patient_id) : null;
    }

    public function population(array $parameters): int
    {
        return Visit::query()->whereIn('status', [VisitStatus::Open, VisitStatus::Closed])->count();
    }
}
