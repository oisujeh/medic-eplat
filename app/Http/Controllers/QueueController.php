<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Http\Resources\ObservationSetResource;
use App\Http\Resources\ServicePointOptionResource;
use App\Models\Module;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The facility floor view: live counts per service point, and a management
 * page per queue for fixing assignment and routing. Attending to patients
 * happens in the module consoles; routing a patient in happens on the chart.
 */
class QueueController extends Controller
{
    /**
     * Overview of every service point with live queue counts.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $modules = Module::query()->get()->keyBy('slug');

        $servicePoints = ServicePoint::active()
            ->withCount([
                'queueEntries as waiting_count' => fn ($q) => $q->where('status', QueueStatus::Waiting),
                'queueEntries as in_service_count' => fn ($q) => $q->where('status', QueueStatus::InService),
            ])
            ->get()
            ->map(function (ServicePoint $sp) use ($user, $modules) {
                $module = $sp->module_slug ? $modules->get($sp->module_slug) : null;
                $canWork = (bool) $user?->canAccessModule($sp->module_slug ?? '');

                return [
                    'name' => $sp->name,
                    'slug' => $sp->slug,
                    'icon' => $sp->icon,
                    'description' => $sp->description,
                    'waiting' => $sp->waiting_count,
                    'in_service' => $sp->in_service_count,
                    'module' => $module?->name,
                    // Where the staff of this point attend to patients.
                    'console_url' => $canWork && $module ? $module->link() : null,
                    'manage_url' => $this->canManage($user, $sp) ? route('queues.show', $sp) : null,
                ];
            });

        return Inertia::render('queues/Index', [
            'servicePoints' => $servicePoints,
        ]);
    }

    /**
     * Management page for a single queue: who holds each patient, with the
     * means to reassign, re-route or cancel.
     */
    public function show(Request $request, ServicePoint $servicePoint): Response
    {
        $user = $request->user();

        abort_unless($servicePoint->is_active && $this->canManage($user, $servicePoint), 403);

        $entries = $servicePoint->queueEntries()
            ->active()
            ->worklistOrder()
            ->with([
                'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth',
                'assignedTo:id,name',
                'routedBy:id,name',
                'visit:id,visit_number',
                'visit.latestObservationSet.observations',
                'visit.latestObservationSet.recordedBy:id,name',
            ])
            ->get()
            ->map(fn (QueueEntry $entry) => $this->presentEntry($entry, $user->id));

        $module = $servicePoint->module_slug ? Module::where('slug', $servicePoint->module_slug)->first() : null;

        return Inertia::render('queues/Show', [
            'servicePoint' => [
                'name' => $servicePoint->name,
                'slug' => $servicePoint->slug,
                'description' => $servicePoint->description,
                'console_url' => $module && $user->canAccessModule($servicePoint->module_slug ?? '') ? $module->link() : null,
            ],
            'entries' => $entries,
            'personnel' => $servicePoint->eligiblePersonnel()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),
            'otherServicePoints' => ServicePointOptionResource::collection(
                ServicePoint::active()->where('id', '!=', $servicePoint->id)->get(),
            ),
            'priorities' => collect(Priority::cases())->map(fn (Priority $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    /**
     * Queue management is open to the point's own staff and to roles that
     * see every module. (The `queues` module itself only grants routing and
     * is held by nearly every role, so it is no gate.)
     */
    private function canManage(?User $user, ServicePoint $servicePoint): bool
    {
        return $user !== null
            && ($user->hasFullModuleAccess() || $user->canAccessModule($servicePoint->module_slug ?? ''));
    }

    /**
     * Present a queue entry for the management list.
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
            'assigned_to_id' => $entry->assigned_to,
            'assigned_to_me' => $entry->assigned_to === $viewerId,
            'routed_by' => $entry->routedBy?->name,
            'visit_number' => $entry->visit?->visit_number,
            'latest_observations' => $entry->visit?->latestObservationSet
                ? ObservationSetResource::make($entry->visit->latestObservationSet)->resolve()
                : null,
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
