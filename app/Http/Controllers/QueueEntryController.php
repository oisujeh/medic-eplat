<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Http\Requests\CompleteQueueEntryRequest;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Services\PatientFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueEntryController extends Controller
{
    public function __construct(private readonly PatientFlowService $flow) {}

    /**
     * Call a waiting patient — the acting staff member takes the entry.
     */
    public function call(Request $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        abort_unless($entry->status === QueueStatus::Waiting, 422);

        $this->flow->call($entry, $request->user());

        return back();
    }

    /**
     * Complete an entry, optionally routing the patient onward.
     */
    public function complete(CompleteQueueEntryRequest $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        abort_unless($entry->status->isActive(), 422);

        $next = $request->filled('next_service_point_id')
            ? ServicePoint::find($request->integer('next_service_point_id'))
            : null;

        $nextAssignee = $next && $request->filled('next_assigned_to')
            ? $next->eligiblePersonnel()->firstWhere('id', $request->integer('next_assigned_to'))
            : null;

        $this->flow->complete(
            entry: $entry,
            actor: $request->user(),
            next: $next,
            nextPriority: Priority::from($request->input('next_priority', Priority::Normal->value)),
            nextNote: $request->input('next_note'),
            nextAssignedTo: $nextAssignee,
        );

        $message = $next
            ? "Completed and routed to {$next->name}."
            : 'Marked as completed.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Cancel an entry.
     */
    public function cancel(Request $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        abort_unless($entry->status->isActive(), 422);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->flow->cancel($entry, $request->user(), $validated['reason'] ?? null);

        return back();
    }

    /**
     * Ensure the acting user is allowed to work this entry's service point.
     */
    private function authorizeEntry(Request $request, QueueEntry $entry): void
    {
        $entry->loadMissing('servicePoint');

        abort_unless(
            (bool) $request->user()?->canAccessModule($entry->servicePoint->module_slug ?? ''),
            403,
        );
    }
}
