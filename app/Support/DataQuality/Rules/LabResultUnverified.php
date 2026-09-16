<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Enums\LabOrderStatus;
use App\Models\LabOrder;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Elapsed;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A specimen received in the laboratory with no verified result to show for
 * it. Nothing reaches the chart until a result is released, so the clinician
 * is waiting without knowing it.
 */
class LabResultUnverified extends DataQualityCheck
{
    public static function key(): string
    {
        return 'lab_result_unverified';
    }

    public static function name(): string
    {
        return 'Laboratory result not released';
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
        return 'A specimen received in the laboratory with no verified result after the time below. Nothing reaches the chart until a result is released, so the clinician waits without knowing it.';
    }

    public static function subjectType(): string
    {
        return LabOrder::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'laboratory-staff';
    }

    public static function defaultParameters(): array
    {
        return ['hours' => 24];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'hours', 'label' => 'Allowed after the specimen arrives', 'type' => 'number', 'suffix' => 'hours', 'min' => 1, 'max' => 336],
        ];
    }

    public static function runsOnWrite(): bool
    {
        return false;
    }

    public function candidates(array $parameters): iterable
    {
        return LabOrder::query()
            ->where('status', LabOrderStatus::InProgress)
            ->whereNotNull('received_at')
            ->where('received_at', '<=', now()->subHours((int) $parameters['hours']))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof LabOrder || $subject->status !== LabOrderStatus::InProgress) {
            return null;
        }

        $received = $subject->received_at;
        $hours = (int) $parameters['hours'];

        if ($received === null || $received->diffInHours(now()) < $hours) {
            return null;
        }

        return new Finding(
            'Specimen received '.Elapsed::since($received).' ago with no result released.',
            ['accession_number' => $subject->accession_number, 'received_at' => $received->toIso8601String()],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof LabOrder ? route('laboratory.show', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return LabOrder::query()
            ->whereIn('status', [LabOrderStatus::InProgress, LabOrderStatus::Completed])
            ->count();
    }
}
