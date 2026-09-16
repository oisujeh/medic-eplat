<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Enums\ReferralStatus;
use App\Models\Referral;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A patient referred out and never heard of again. Without the counter-
 * referral nobody here knows whether they arrived, and the loop the referral
 * letter opened stays open.
 */
class ReferralWithoutFeedback extends DataQualityCheck
{
    public static function key(): string
    {
        return 'referral_without_feedback';
    }

    public static function name(): string
    {
        return 'Referral without feedback';
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
        return 'A referral issued more than the days below ago with nothing back from the receiving facility, so nobody here knows whether the patient arrived.';
    }

    public static function subjectType(): string
    {
        return Referral::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'records-officer';
    }

    public static function defaultParameters(): array
    {
        return ['days' => 14];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'days', 'label' => 'Allowed before feedback', 'type' => 'number', 'suffix' => 'days', 'min' => 1, 'max' => 180],
        ];
    }

    public static function runsOnWrite(): bool
    {
        return false;
    }

    public function candidates(array $parameters): iterable
    {
        return Referral::query()
            ->where('status', ReferralStatus::Issued)
            ->where('referred_at', '<=', now()->subDays((int) $parameters['days']))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof Referral || $subject->status !== ReferralStatus::Issued) {
            return null;
        }

        $days = (int) $parameters['days'];
        $waiting = (int) $subject->referred_at->diffInDays(now());

        if ($waiting < $days) {
            return null;
        }

        return new Finding(
            "No feedback on referral {$subject->referral_number} to {$subject->destination_facility} after {$waiting} days.",
            ['referral_number' => $subject->referral_number, 'destination' => $subject->destination_facility, 'days_waiting' => $waiting],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof Referral ? route('referrals.show', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return Referral::query()->count();
    }
}
