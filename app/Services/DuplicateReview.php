<?php

namespace App\Services;

use App\Enums\MatchCandidateStatus;
use App\Models\Patient;
use App\Models\PatientMatchCandidate;
use App\Models\User;
use App\Support\MatchResult;
use Illuminate\Database\Eloquent\Builder;

/**
 * The review queue: pairs of records the matcher scored high enough that a
 * person should decide whether they are one patient.
 */
class DuplicateReview
{
    public function __construct(private readonly PatientMatcher $matcher) {}

    /**
     * Re-score a record against the register and queue every probable
     * match. A pair someone has already dismissed is left alone.
     */
    public function refresh(Patient $patient): void
    {
        if ($patient->merged_into_id !== null) {
            return;
        }

        foreach ($this->matcher->candidatesFor($patient) as $result) {
            if (! $result->level->isQueued()) {
                continue;
            }

            $this->queue($patient, $result);
        }
    }

    public function dismiss(PatientMatchCandidate $candidate, User $reviewer, ?string $notes = null): void
    {
        $candidate->update([
            'status' => MatchCandidateStatus::Dismissed,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * @return Builder<PatientMatchCandidate>
     */
    public function pending(): Builder
    {
        return PatientMatchCandidate::query()->pending()->orderByDesc('score')->orderBy('id');
    }

    public function pendingCount(): int
    {
        return $this->pending()->count();
    }

    private function queue(Patient $patient, MatchResult $result): void
    {
        [$low, $high] = $patient->id < $result->patient->id
            ? [$patient->id, $result->patient->id]
            : [$result->patient->id, $patient->id];

        $existing = PatientMatchCandidate::query()
            ->where('patient_id', $low)
            ->where('candidate_id', $high)
            ->first();

        $attributes = [
            'score' => $result->score,
            'level' => $result->level,
            'signals' => $result->signalValues(),
        ];

        if ($existing === null) {
            PatientMatchCandidate::create([...$attributes, 'patient_id' => $low, 'candidate_id' => $high, 'status' => MatchCandidateStatus::Pending]);

            return;
        }

        if ($existing->status === MatchCandidateStatus::Pending) {
            $existing->update($attributes);
        }
    }
}
