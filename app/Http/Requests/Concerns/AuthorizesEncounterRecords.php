<?php

namespace App\Http\Requests\Concerns;

use App\Models\Encounter;

/**
 * Requests that add or change clinical records within an encounter are
 * authorised by the encounter's `document` ability.
 */
trait AuthorizesEncounterRecords
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('document', $this->encounter());
    }

    public function encounter(): Encounter
    {
        /** @var Encounter */
        return $this->route('encounter');
    }
}
