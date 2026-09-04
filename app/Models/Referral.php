<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ReferralStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An outbound referral to another facility.
 *
 * @property int $id
 * @property string $referral_number
 * @property int $patient_id
 * @property int|null $encounter_id
 * @property int|null $referred_by
 * @property Priority $urgency
 * @property string $destination_facility
 * @property string|null $destination_department
 * @property string|null $destination_contact
 * @property string $reason
 * @property string|null $diagnosis
 * @property string|null $clinical_summary
 * @property string|null $treatment_given
 * @property ReferralStatus $status
 * @property string|null $feedback
 * @property Carbon|null $feedback_at
 * @property int|null $closed_by
 * @property Carbon $referred_at
 * @property Carbon|null $printed_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'referral_number', 'patient_id', 'encounter_id', 'referred_by', 'urgency',
    'destination_facility', 'destination_department', 'destination_contact',
    'reason', 'diagnosis', 'clinical_summary', 'treatment_given',
    'status', 'feedback', 'feedback_at', 'closed_by', 'referred_at', 'printed_at',
])]
class Referral extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urgency' => Priority::class,
            'status' => ReferralStatus::class,
            'feedback_at' => 'datetime',
            'referred_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    /**
     * Referrals still awaiting the receiving facility.
     *
     * @param  Builder<Referral>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [ReferralStatus::Issued->value, ReferralStatus::Accepted->value]);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function auditLabel(): string
    {
        return "Referral {$this->referral_number} to {$this->destination_facility}";
    }
}
