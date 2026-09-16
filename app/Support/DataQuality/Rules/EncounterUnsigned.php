<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Enums\EncounterStatus;
use App\Models\Encounter;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Elapsed;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A consultation left open long after the patient went home. Nothing in it
 * counts until it is signed: not the diagnosis, not the return, not the bill.
 */
class EncounterUnsigned extends DataQualityCheck
{
    public static function key(): string
    {
        return 'encounter_unsigned';
    }

    public static function name(): string
    {
        return 'Encounter left unsigned';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Timeliness;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Major;
    }

    public static function description(): string
    {
        return 'A consultation still in progress after the time below. Nothing in an unsigned encounter counts: not the diagnosis, not the return, not the bill.';
    }

    public static function subjectType(): string
    {
        return Encounter::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'physician';
    }

    public static function defaultParameters(): array
    {
        return ['hours' => 24];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'hours', 'label' => 'Allowed before signing', 'type' => 'number', 'suffix' => 'hours', 'min' => 1, 'max' => 336],
        ];
    }

    public static function runsOnWrite(): bool
    {
        return false;
    }

    public function candidates(array $parameters): iterable
    {
        return Encounter::query()
            ->where('status', EncounterStatus::InProgress)
            ->where('created_at', '<=', now()->subHours((int) $parameters['hours']))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Encounter || $subject->status !== EncounterStatus::InProgress) {
            return null;
        }

        $started = $subject->started_at ?? $subject->created_at;
        $hours = (int) $parameters['hours'];

        if ($started === null || $started->diffInHours(now()) < $hours) {
            return null;
        }

        return new Finding(
            'Encounter has been open for '.Elapsed::since($started).' without being signed.',
            ['started_at' => $started->toIso8601String()],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Encounter ? route('encounters.show', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return Encounter::query()->whereIn('status', [EncounterStatus::InProgress, EncounterStatus::Signed])->count();
    }
}
