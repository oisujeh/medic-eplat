<?php

namespace App\Support\DataQuality;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;

/**
 * One data-quality check: the code that decides whether a record is sound.
 *
 * What the check judges lives here; what a facility may tune about it —
 * severity, the role that owns the work, thresholds, whether it runs at all —
 * lives in its App\Models\DataQualityRule row. A check declares its defaults
 * so a fresh installation can seed that row from the code.
 */
abstract class DataQualityCheck
{
    /**
     * The stable key tying the check to its registry row.
     */
    abstract public static function key(): string;

    abstract public static function name(): string;

    abstract public static function dimension(): DataQualityDimension;

    abstract public static function defaultSeverity(): DataQualitySeverity;

    /**
     * What the check looks for and why it matters, shown on the rules screen.
     */
    abstract public static function description(): string;

    /**
     * The model class the check judges.
     *
     * @return class-string<Model>
     */
    abstract public static function subjectType(): string;

    /**
     * The role slug whose queue the issues land in, or null for everyone with
     * the module.
     */
    public static function defaultOwnerRole(): ?string
    {
        return null;
    }

    /**
     * The thresholds the check reads, with the values a fresh installation
     * starts from.
     *
     * @return array<string, mixed>
     */
    public static function defaultParameters(): array
    {
        return [];
    }

    /**
     * How each parameter is presented on the rules screen.
     *
     * @return array<int, array{key: string, label: string, type: string, suffix?: string, min?: int, max?: int}>
     */
    public static function parameterFields(): array
    {
        return [];
    }

    /**
     * Whether the check can decide as the record is written. Checks that
     * measure elapsed time cannot: nothing is late the moment it happens, so
     * they are left to the nightly sweep.
     */
    public static function runsOnWrite(): bool
    {
        return true;
    }

    /**
     * The records the nightly sweep examines, streamed rather than loaded.
     *
     * @param  array<string, mixed>  $parameters
     * @return iterable<int, Model>
     */
    abstract public function candidates(array $parameters): iterable;

    /**
     * Judge one record. Null means it passes.
     *
     * @param  array<string, mixed>  $parameters
     */
    abstract public function check(Model $subject, array $parameters): ?Finding;

    /**
     * Where a person goes to put the record right.
     */
    abstract public function href(Model $subject): ?string;

    /**
     * How many records the rule judges, as the denominator of its pass rate.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function population(array $parameters): int
    {
        $model = static::subjectType();

        return $model::query()->count();
    }

    /**
     * The patient the issue is about, when there is one. Checks whose subject
     * reaches its patient another way override this.
     */
    public function patientIdFor(Model $subject): ?int
    {
        if ($subject instanceof Patient) {
            return (int) $subject->getKey();
        }

        $patientId = $subject->getAttribute('patient_id');

        return $patientId === null ? null : (int) $patientId;
    }

    /**
     * Whether the check judges records of this class.
     */
    public function judges(Model $subject): bool
    {
        return is_a($subject, static::subjectType());
    }
}
