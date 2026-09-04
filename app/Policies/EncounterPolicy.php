<?php

namespace App\Policies;

use App\Models\Encounter;
use App\Models\User;

/**
 * Who may read, document, sign and amend an encounter. Access follows the
 * module that governs the encounter type. While the encounter is open, a
 * queue-assigned patient is visible only to the assignee (and to roles that
 * see every module). Once signed, the note is locked: it can be read by
 * anyone in the module and extended only through addenda.
 */
class EncounterPolicy
{
    /**
     * Open the encounter screen.
     */
    public function view(User $user, Encounter $encounter): bool
    {
        if (! $this->inModule($user, $encounter)) {
            return false;
        }

        return $encounter->isOpen() ? $this->holdsPatient($user, $encounter) : true;
    }

    /**
     * Add or change documentation and clinical records on the encounter.
     * Only an open encounter can be documented.
     */
    public function document(User $user, Encounter $encounter): bool
    {
        return $encounter->isOpen()
            && $this->inModule($user, $encounter)
            && $this->holdsPatient($user, $encounter);
    }

    /**
     * Sign the encounter off.
     */
    public function sign(User $user, Encounter $encounter): bool
    {
        return $this->document($user, $encounter);
    }

    /**
     * Append an addendum to a signed encounter.
     */
    public function addend(User $user, Encounter $encounter): bool
    {
        return $encounter->isSigned() && $this->inModule($user, $encounter);
    }

    private function inModule(User $user, Encounter $encounter): bool
    {
        return $user->canAccessModule($encounter->type->module());
    }

    /**
     * Whether the patient is the user's to work: unassigned, assigned to
     * them, or the user sees every module.
     */
    private function holdsPatient(User $user, Encounter $encounter): bool
    {
        if ($user->hasFullModuleAccess()) {
            return true;
        }

        $entry = $encounter->queueEntry;

        return $entry === null
            || $entry->assigned_to === null
            || $entry->assigned_to === $user->id;
    }
}
