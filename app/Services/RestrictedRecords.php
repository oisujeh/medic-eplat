<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Who may open a restricted patient record (PRD REG-20, USR-09).
 *
 * Staff holding the break-glass permission open it outright. Anyone else
 * who can reach patient records must first give a reason; the reason is
 * written to the audit trail and unlocks the record for the rest of the
 * session, where the facility admin can review every such event.
 */
class RestrictedRecords
{
    public const SESSION_KEY = 'break_glass';

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * Whether the user may open the patient's record right now.
     */
    public function canOpen(Patient $patient, User $user, Session $session): bool
    {
        if (! $patient->isRestricted()) {
            return true;
        }

        if ($user->hasPermission(Permission::BreakGlass)) {
            return true;
        }

        return in_array($patient->id, $session->get(self::SESSION_KEY, []), true);
    }

    /**
     * Record a break-the-glass reason and unlock the record for this session.
     */
    public function breakGlass(Patient $patient, User $user, Session $session, string $reason): void
    {
        $this->audit->record(
            action: AuditLog::ACTION_BREAK_GLASS,
            subject: $patient,
            patientId: $patient->id,
            reason: $reason,
        );

        $unlocked = $session->get(self::SESSION_KEY, []);
        $unlocked[] = $patient->id;
        $session->put(self::SESSION_KEY, array_values(array_unique($unlocked)));
    }
}
