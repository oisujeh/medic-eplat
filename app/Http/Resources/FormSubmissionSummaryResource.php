<?php

namespace App\Http\Resources;

use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A filled form as listed on an encounter or a chart: which form, what
 * state it is in, who touched it last, and where to open it.
 *
 * @mixin FormSubmission
 */
class FormSubmissionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $by = $this->completedBy ?? $this->filledBy;
        $at = $this->completed_at ?? $this->last_saved_at ?? $this->started_at;

        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'form_name' => $this->form?->name,
            'form_slug' => $this->form?->slug,
            'version' => $this->version?->version,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'entry_mode' => $this->entry_mode->value,
            'by' => $by?->name,
            'at' => $at->toIso8601String(),
            'at_label' => $at->isoFormat('D MMM YYYY, h:mm a'),
            'void_reason' => $this->void_reason,
            'url' => route('forms.show', $this->id),
        ];
    }
}
