<?php

namespace App\Support\DataQuality\Rules;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Enums\MatchCandidateStatus;
use App\Models\PatientMatchCandidate;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Model;

/**
 * A probable duplicate that has sat in the review queue without a decision.
 * Until somebody merges or dismisses it, one patient's history is in two
 * places and neither is complete.
 */
class DuplicateReviewAgeing extends DataQualityCheck
{
    public static function key(): string
    {
        return 'duplicate_review_ageing';
    }

    public static function name(): string
    {
        return 'Duplicate awaiting review';
    }

    public static function dimension(): DataQualityDimension
    {
        return DataQualityDimension::Duplicate;
    }

    public static function defaultSeverity(): DataQualitySeverity
    {
        return DataQualitySeverity::Major;
    }

    public static function description(): string
    {
        return 'A probable duplicate left in the review queue past the age below. Until it is merged or dismissed, one patient\'s history sits in two folders and neither is complete.';
    }

    public static function subjectType(): string
    {
        return PatientMatchCandidate::class;
    }

    public static function defaultOwnerRole(): ?string
    {
        return 'records-officer';
    }

    public static function defaultParameters(): array
    {
        return ['days' => 7];
    }

    public static function parameterFields(): array
    {
        return [
            ['key' => 'days', 'label' => 'Allowed before review', 'type' => 'number', 'suffix' => 'days', 'min' => 1, 'max' => 90],
        ];
    }

    public static function runsOnWrite(): bool
    {
        return false;
    }

    public function candidates(array $parameters): iterable
    {
        return PatientMatchCandidate::query()
            ->where('status', MatchCandidateStatus::Pending)
            ->where('created_at', '<=', now()->subDays((int) $parameters['days']))
            ->lazyById(500);
    }

    public function check(Model $subject, array $parameters): ?Finding
    {
        if (! $subject instanceof PatientMatchCandidate || $subject->status !== MatchCandidateStatus::Pending) {
            return null;
        }

        $days = (int) $parameters['days'];
        $waiting = (int) ($subject->created_at?->diffInDays(now()) ?? 0);

        if ($waiting < $days) {
            return null;
        }

        return new Finding(
            "Possible duplicate has waited {$waiting} days for a decision.",
            ['score' => $subject->score, 'level' => $subject->level->value, 'days_waiting' => $waiting],
        );
    }

    public function href(Model $subject): ?string
    {
        return $subject instanceof PatientMatchCandidate ? route('patients.duplicates.show', $subject) : null;
    }

    public function population(array $parameters): int
    {
        return PatientMatchCandidate::query()->where('status', MatchCandidateStatus::Pending)->count();
    }
}
