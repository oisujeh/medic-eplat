<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\CaseNotificationStatus;
use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\SurveillanceCase;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Elapsed;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * An immediately notifiable case the DSNO has still not been told about. The
 * deadline is the one the catalogue set when the case was opened, so this
 * check reads the case rather than the catalogue as it stands today.
 */
class CaseNotificationOverdue extends DataQualityCheck
{
    public static function key(): string
    {
        return 'case_notification_overdue';
    }

    public static function name(): string
    {
        return 'IDSR notification past its deadline';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Timeliness;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Critical;
    }

    public static function description(): string
    {
        return 'A notifiable case whose deadline for reaching the LGA DSNO has passed with no notification recorded. The deadline is the one in force when the case was opened.';
    }

    public static function subjectType(): string
    {
        return SurveillanceCase::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'records-officer';
    }

    public static function runsOnWrite(): bool
    {
        return false;
    }

    public function candidates(array $parameters): iterable
    {
        return SurveillanceCase::query()->overdue()->with('disease:id,name')->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof SurveillanceCase || ! $subject->isOverdue()) {
            return null;
        }

        $due = $subject->notification_due_at;

        return new Finding(
            $subject->disease->name.' has been notifiable since '.($due === null ? 'detection' : Elapsed::since($due).' ago').' with no notification recorded.',
            ['disease' => $subject->disease->name, 'due_at' => $due?->toIso8601String()],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof SurveillanceCase ? route('surveillance.show', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return SurveillanceCase::query()
            ->open()
            ->where('notification_status', '!=', CaseNotificationStatus::Weekly->value)
            ->count();
    }
}
