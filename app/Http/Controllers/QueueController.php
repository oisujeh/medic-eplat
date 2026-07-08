<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    /**
     * Overview of every service point with live queue counts.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $servicePoints = ServicePoint::active()
            ->withCount([
                'queueEntries as waiting_count' => fn ($q) => $q->where('status', QueueStatus::Waiting),
                'queueEntries as in_service_count' => fn ($q) => $q->where('status', QueueStatus::InService),
            ])
            ->get()
            ->map(fn (ServicePoint $sp) => [
                'name' => $sp->name,
                'slug' => $sp->slug,
                'icon' => $sp->icon,
                'description' => $sp->description,
                'waiting' => $sp->waiting_count,
                'in_service' => $sp->in_service_count,
                'can_work' => (bool) $user?->canAccessModule($sp->module_slug ?? ''),
            ]);

        return Inertia::render('queues/Index', [
            'servicePoints' => $servicePoints,
        ]);
    }

    /**
     * Worklist for a single service point.
     */
    public function show(Request $request, ServicePoint $servicePoint): Response
    {
        $user = $request->user();

        abort_unless(
            $servicePoint->is_active && $user?->canAccessModule($servicePoint->module_slug ?? ''),
            403,
        );

        // Oversight roles (Super Admin / CMD with full access) see the whole
        // queue; regular personnel see only patients assigned to them plus the
        // unassigned shared pool.
        $seesAll = $user->hasFullModuleAccess();

        $entries = $servicePoint->queueEntries()
            ->active()
            ->when(! $seesAll, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('assigned_to')
                ->orWhere('assigned_to', $user->id)))
            ->worklistOrder()
            ->with([
                'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth',
                'assignedTo:id,name',
                'routedBy:id,name',
                'visit:id,visit_number',
                'visit.vitalSigns.recordedBy:id,name',
            ])
            ->get()
            ->map(fn (QueueEntry $entry) => $this->presentEntry($entry, $user->id));

        return Inertia::render('queues/Show', [
            'servicePoint' => [
                'name' => $servicePoint->name,
                'slug' => $servicePoint->slug,
                'description' => $servicePoint->description,
                'captures_vitals' => $servicePoint->captures_vitals,
            ],
            'entries' => $entries,
            'seesAll' => $seesAll,
            'onwardServicePoints' => $this->onwardServicePoints($servicePoint),
            'priorities' => collect(Priority::cases())->map(fn (Priority $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    /**
     * Active service points other than the current one, each with the staff
     * that may be assigned there.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function onwardServicePoints(ServicePoint $current): \Illuminate\Support\Collection
    {
        return ServicePoint::active()
            ->where('id', '!=', $current->id)
            ->get()
            ->map(fn (ServicePoint $sp) => [
                'id' => $sp->id,
                'name' => $sp->name,
                'personnel' => $sp->eligiblePersonnel()
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values(),
            ]);
    }

    /**
     * Present a queue entry for the worklist.
     *
     * @return array<string, mixed>
     */
    private function presentEntry(QueueEntry $entry, int $viewerId): array
    {
        return [
            'id' => $entry->id,
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'priority' => $entry->priority->value,
            'priority_label' => $entry->priority->label(),
            'note' => $entry->note,
            'queued_at' => $entry->queued_at?->diffForHumans(),
            'started_at' => $entry->started_at?->diffForHumans(),
            'assigned_to' => $entry->assignedTo?->name,
            'assigned_to_me' => $entry->assigned_to === $viewerId,
            'routed_by' => $entry->routedBy?->name,
            'visit_number' => $entry->visit?->visit_number,
            'latest_vitals' => $entry->visit?->vitalSigns->first()?->summary(),
            'patient' => [
                'file_number' => $entry->patient->file_number,
                'name' => $entry->patient->fullName(),
                'initials' => $entry->patient->initials(),
                'sex' => $entry->patient->sex,
                'age' => $entry->patient->age(),
                'url' => route('patients.show', $entry->patient_id),
            ],
        ];
    }
}
