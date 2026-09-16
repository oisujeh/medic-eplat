<?php

namespace App\Models;

use App\Enums\MatchCandidateStatus;
use App\Enums\MatchLevel;
use App\Enums\MatchSignal;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pair of patient records that may describe one person, waiting for a
 * records officer to merge or dismiss. `patient_id` is the lower id of the
 * pair.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $candidate_id
 * @property int $score
 * @property MatchLevel $level
 * @property list<string> $signals
 * @property MatchCandidateStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $notes
 */
#[Fillable(['patient_id', 'candidate_id', 'score', 'level', 'signals', 'status', 'reviewed_by', 'reviewed_at', 'notes'])]
class PatientMatchCandidate extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'level' => MatchLevel::class,
            'signals' => 'array',
            'status' => MatchCandidateStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PatientMatchCandidate>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', MatchCandidateStatus::Pending->value);
    }

    /**
     * @param  Builder<PatientMatchCandidate>  $query
     */
    #[Scope]
    protected function involving(Builder $query, Patient $patient): void
    {
        $query->where(fn (Builder $q) => $q->where('patient_id', $patient->id)->orWhere('candidate_id', $patient->id));
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'candidate_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The readable reasons the pair was flagged.
     *
     * @return list<string>
     */
    public function signalLabels(): array
    {
        return array_map(
            fn (string $value) => MatchSignal::tryFrom($value)?->label() ?? $value,
            $this->signals ?? [],
        );
    }

    public function auditLabel(): string
    {
        return "Possible duplicate: patients #{$this->patient_id} and #{$this->candidate_id}";
    }
}
