<?php

namespace App\Events;

use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A clinical form was completed (or completed again after editing), and its
 * answers now stand in the flattened value table.
 */
class FormCompleted extends DomainEvent
{
    public function __construct(public readonly FormSubmission $submission, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->submission;
    }

    public function payload(): array
    {
        return [
            'form_id' => $this->submission->form_id,
            'form_slug' => $this->submission->form?->slug,
            'form_version_id' => $this->submission->form_version_id,
            'encounter_id' => $this->submission->encounter_id,
            'visit_id' => $this->submission->visit_id,
            'entry_mode' => $this->submission->entry_mode->value,
            'completed_at' => $this->submission->completed_at?->toIso8601String(),
        ];
    }
}
