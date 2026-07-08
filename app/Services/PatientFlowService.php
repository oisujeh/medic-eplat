<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatientFlowService
{
    /**
     * Route a patient to a service point, opening a visit if one is not active.
     *
     * This is the entry point used by Records to place a patient in a queue.
     */
    public function route(
        Patient $patient,
        ServicePoint $servicePoint,
        User $actor,
        Priority $priority = Priority::Normal,
        ?string $note = null,
        ?string $visitReason = null,
        ?User $assignedTo = null,
    ): QueueEntry {
        return DB::transaction(function () use ($patient, $servicePoint, $actor, $priority, $note, $visitReason, $assignedTo) {
            $visit = $patient->openVisit() ?? $this->openVisit($patient, $actor, $visitReason);

            return $this->pushEntry($visit, $servicePoint, $actor, $priority, $note, $assignedTo);
        });
    }

    /**
     * Mark a waiting entry as being attended to by the acting staff member.
     */
    public function call(QueueEntry $entry, User $actor): QueueEntry
    {
        $entry->update([
            'status' => QueueStatus::InService,
            'assigned_to' => $actor->id,
            'started_at' => $entry->started_at ?? now(),
        ]);

        return $entry;
    }

    /**
     * Complete an entry, optionally routing the patient onward to the next
     * service point in the same visit.
     */
    public function complete(
        QueueEntry $entry,
        User $actor,
        ?ServicePoint $next = null,
        Priority $nextPriority = Priority::Normal,
        ?string $nextNote = null,
        ?User $nextAssignedTo = null,
    ): QueueEntry {
        return DB::transaction(function () use ($entry, $actor, $next, $nextPriority, $nextNote, $nextAssignedTo) {
            $entry->update([
                'status' => QueueStatus::Completed,
                'assigned_to' => $entry->assigned_to ?? $actor->id,
                'started_at' => $entry->started_at ?? now(),
                'completed_at' => now(),
            ]);

            if ($next) {
                $this->pushEntry($entry->visit, $next, $actor, $nextPriority, $nextNote, $nextAssignedTo);
            }

            return $entry->refresh();
        });
    }

    /**
     * Cancel an entry (e.g. routed in error or patient left).
     */
    public function cancel(QueueEntry $entry, User $actor, ?string $reason = null): QueueEntry
    {
        $entry->update([
            'status' => QueueStatus::Cancelled,
            'note' => $reason ?: $entry->note,
        ]);

        return $entry;
    }

    /**
     * Close a visit, cancelling any still-active queue entries.
     */
    public function closeVisit(Visit $visit, User $actor): Visit
    {
        return DB::transaction(function () use ($visit, $actor) {
            $visit->queueEntries()
                ->whereIn('status', [QueueStatus::Waiting, QueueStatus::InService])
                ->update(['status' => QueueStatus::Cancelled]);

            $visit->update([
                'status' => VisitStatus::Closed,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);

            return $visit;
        });
    }

    /**
     * Open a new visit for a patient, generating a human-readable visit number.
     */
    protected function openVisit(Patient $patient, User $actor, ?string $reason): Visit
    {
        $visit = new Visit();
        $visit->patient_id = $patient->id;
        $visit->status = VisitStatus::Open;
        $visit->reason = $reason;
        $visit->opened_by = $actor->id;
        $visit->opened_at = now();
        $visit->visit_number = 'TMP-'.Str::uuid();
        $visit->save();

        $visit->update([
            'visit_number' => sprintf('V/%d/%06d', $visit->opened_at->year, $visit->id),
        ]);

        return $visit;
    }

    /**
     * Create a waiting queue entry for a visit at a service point.
     */
    protected function pushEntry(
        Visit $visit,
        ServicePoint $servicePoint,
        User $actor,
        Priority $priority,
        ?string $note,
        ?User $assignedTo = null,
    ): QueueEntry {
        return QueueEntry::create([
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'service_point_id' => $servicePoint->id,
            'status' => QueueStatus::Waiting,
            'priority' => $priority,
            'note' => $note,
            'routed_by' => $actor->id,
            'assigned_to' => $assignedTo?->id,
            'queued_at' => now(),
        ]);
    }
}
