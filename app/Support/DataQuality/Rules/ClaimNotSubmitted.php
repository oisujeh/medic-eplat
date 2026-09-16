<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\ClaimStatus;
use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Claim;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A claim prepared and never sent. Payers hold facilities to a submission
 * window, and money the facility has already earned sits behind it.
 */
class ClaimNotSubmitted extends DataQualityCheck
{
    public static function key(): string
    {
        return 'claim_not_submitted';
    }

    public static function name(): string
    {
        return 'Claim not submitted';
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
        return 'A claim still in draft more than the days below after the service. Payers hold facilities to a submission window, and money already earned sits behind it.';
    }

    public static function subjectType(): string
    {
        return Claim::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'cashier';
    }

    public static function defaultParameters(): array
    {
        return ['days' => 7];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'days', 'label' => 'Allowed before submission', 'type' => 'number', 'suffix' => 'days', 'min' => 1, 'max' => 120],
        ];
    }

    public static function runsOnWrite(): bool
    {
        return false;
    }

    public function candidates(array $parameters): iterable
    {
        return Claim::query()
            ->where('status', ClaimStatus::Draft)
            ->where('service_date', '<=', now()->subDays((int) $parameters['days'])->toDateString())
            ->with('payer:id,name')
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Claim || $subject->status !== ClaimStatus::Draft) {
            return null;
        }

        $days = (int) $parameters['days'];
        $waiting = (int) $subject->service_date->diffInDays(now());

        if ($waiting < $days) {
            return null;
        }

        return new Finding(
            "Claim {$subject->claim_number} has been in draft for {$waiting} days since the service.",
            ['claim_number' => $subject->claim_number, 'payer' => $subject->payer?->name, 'days_waiting' => $waiting],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Claim ? route('claims.show', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return Claim::query()->count();
    }
}
