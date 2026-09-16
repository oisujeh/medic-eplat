<?php

namespace App\Http\Resources;

use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Referral
 */
class ReferralResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referral_number' => $this->referral_number,
            'encounter_id' => $this->encounter_id,
            'urgency' => $this->urgency->value,
            'urgency_label' => $this->urgency->label(),
            'destination_facility' => $this->destination_facility,
            'destination_department' => $this->destination_department,
            'destination_contact' => $this->destination_contact,
            'reason' => $this->reason,
            'diagnosis' => $this->diagnosis,
            'clinical_summary' => $this->clinical_summary,
            'treatment_given' => $this->treatment_given,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'is_open' => $this->status->isOpen(),
            'feedback' => $this->feedback,
            'feedback_at' => $this->feedback_at?->isoFormat('D MMM YYYY, HH:mm'),
            'referred_by' => $this->whenLoaded('referredBy', fn () => $this->referredBy?->name),
            'closed_by' => $this->whenLoaded('closedBy', fn () => $this->closedBy?->name),
            'referred_at' => $this->referred_at->isoFormat('D MMM YYYY, HH:mm'),
            'printed_at' => $this->printed_at?->isoFormat('D MMM YYYY, HH:mm'),
            'urls' => [
                'show' => route('referrals.show', $this->id),
                'letter' => route('referrals.letter', $this->id),
                'status' => route('referrals.status', $this->id),
            ],
        ];
    }
}
