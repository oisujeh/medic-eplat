<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Services\PatientFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Supervisory actions on a queue entry. Attending to a patient happens in
 * the module consoles, which open an encounter; this controller only fixes
 * the queue itself: who holds the patient, where they are queued, or
 * whether they should be queued at all.
 */
class QueueEntryController extends Controller
{
    public function __construct(private readonly PatientFlowService $flow) {}

    /**
     * Hand the entry to a named staff member, or back to the unassigned pool.
     */
    public function assign(Request $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        abort_unless($entry->status->isActive(), 422);

        $data = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $assignee = filled($data['assigned_to'] ?? null)
            ? $entry->servicePoint->eligiblePersonnel()->firstWhere('id', (int) $data['assigned_to'])
            : null;

        if (filled($data['assigned_to'] ?? null) && ! $assignee) {
            return back()->withErrors(['assigned_to' => 'That staff member does not work this service point.']);
        }

        $this->flow->assign($entry, $assignee);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $assignee ? "Assigned to {$assignee->name}." : 'Returned to the unassigned pool.',
        ]);

        return back();
    }

    /**
     * Move a misrouted entry to another service point: this one is cancelled
     * and a fresh entry is queued there.
     */
    public function reroute(Request $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        abort_unless($entry->status->isActive(), 422);

        $data = $request->validate([
            'service_point_id' => ['required', Rule::exists('service_points', 'id')->where('is_active', true)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $next = ServicePoint::query()->findOrFail((int) $data['service_point_id']);

        $this->flow->reroute(
            entry: $entry,
            next: $next,
            actor: $request->user(),
            priority: Priority::from($data['priority'] ?? $entry->priority->value),
            note: $data['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Re-routed to {$next->name}."]);

        return back();
    }

    /**
     * Cancel an entry (routed in error, or the patient left).
     */
    public function cancel(Request $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        abort_unless($entry->status->isActive(), 422);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->flow->cancel($entry, $request->user(), $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Queue entry cancelled.']);

        return back();
    }

    /**
     * Queue management is open to the staff of the entry's service point and
     * to roles that see every module.
     */
    private function authorizeEntry(Request $request, QueueEntry $entry): void
    {
        $entry->loadMissing('servicePoint');
        $user = $request->user();

        abort_unless(
            $user && ($user->hasFullModuleAccess() || $user->canAccessModule($entry->servicePoint->module_slug ?? '')),
            403,
        );
    }
}
