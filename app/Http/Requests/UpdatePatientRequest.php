<?php

namespace App\Http\Requests;

/**
 * Editing a patient's demographics and coverage uses the same rules as
 * registration; only the authorisation differs in intent (Records revising a
 * record rather than opening one).
 */
class UpdatePatientRequest extends StorePatientRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessModule('registration');
    }
}
