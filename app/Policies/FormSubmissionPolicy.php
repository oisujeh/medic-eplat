<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FormSubmission;
use App\Models\User;

/**
 * Who may read, fill and void a filled form (PRD FRM-09). Access follows
 * the encounter the form was filled on: whoever may document the encounter
 * may fill its forms, provided the form is offered to a role they hold;
 * voiding is a permission of its own and needs a reason.
 */
class FormSubmissionPolicy
{
    public function view(User $user, FormSubmission $submission): bool
    {
        $encounter = $submission->encounter;

        return $encounter !== null
            ? $user->can('view', $encounter)
            : $user->canAccessModule('patient-records');
    }

    public function fill(User $user, FormSubmission $submission): bool
    {
        $encounter = $submission->encounter;

        return $submission->status->isEditable()
            && $encounter !== null
            && $user->can('document', $encounter)
            && $submission->form->canBeFilledBy($user);
    }

    public function void(User $user, FormSubmission $submission): bool
    {
        return $submission->status->isEditable()
            && $user->hasPermission(Permission::VoidForms)
            && $this->view($user, $submission);
    }
}
