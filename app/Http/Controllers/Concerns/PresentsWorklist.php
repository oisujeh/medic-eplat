<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\EncounterType;
use App\Http\Resources\ObservationSetResource;
use App\Models\Encounter;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\User;

/**
 * The console worklist shared by the clinical and nursing modules: patients
 * waiting at the module's service points, and the user's recently signed
 * encounters.
 */
trait PresentsWorklist
{
    /**
     * Active queue entries at the module's service points. Oversight roles
     * see the whole list; everyone else sees their own patients plus the
     * unassigned pool.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function worklistFor(string $module, User $user, string $openRoute): array
    {
        $seesAll = $user->hasFullModuleAccess();

        return QueueEntry::query()
            ->whereIn('service_point_id', ServicePoint::active()->where('module_slug', $module)->pluck('id'))
            ->active()
            ->when(! $seesAll, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('assigned_to')
                ->orWhere('assigned_to', $user->id)))
            ->worklistOrder()
            ->with([
                'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth',
                'servicePoint:id,name',
                'assignedTo:id,name',
                'visit:id,visit_number',
                'visit.latestObservationSet.observations',
                'visit.latestObservationSet.recordedBy:id,name',
            ])
            ->get()
            ->map(fn (QueueEntry $entry) => [
                'id' => $entry->id,
                'status' => $entry->status->value,
                'status_label' => $entry->status->label(),
                'priority' => $entry->priority->value,
                'priority_label' => $entry->priority->label(),
                'service_point' => $entry->servicePoint->name,
                'assigned_to' => $entry->assignedTo?->name,
                'assigned_to_me' => $entry->assigned_to === $user->id,
                'waiting_since' => $entry->queued_at?->diffForHumans(),
                'latest_observations' => $entry->visit?->latestObservationSet
                    ? ObservationSetResource::make($entry->visit->latestObservationSet)->resolve()
                    : null,
                'open_url' => route($openRoute, $entry),
                'patient' => [
                    'name' => $entry->patient->fullName(),
                    'initials' => $entry->patient->initials(),
                    'file_number' => $entry->patient->file_number,
                    'sex' => $entry->patient->sex,
                    'age' => $entry->patient->age(),
                ],
            ])
            ->all();
    }

    /**
     * The user's most recently signed encounters of the given types.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function recentEncountersFor(User $user, EncounterType ...$types): array
    {
        return Encounter::query()
            ->where('author_id', $user->id)
            ->whereIn('type', $types)
            ->signed()
            ->latest('signed_at')
            ->take(8)
            ->with(['patient:id,file_number,surname,first_name,other_names', 'servicePoint:id,name'])
            ->get()
            ->map(fn (Encounter $e) => [
                'id' => $e->id,
                'patient_name' => $e->patient->fullName(),
                'file_number' => $e->patient->file_number,
                'summary' => $e->assessment ?: $e->servicePoint?->name,
                'signed_at' => $e->signed_at?->diffForHumans(),
                'url' => route('encounters.show', $e),
                'patient_url' => route('patients.show', $e->patient_id),
            ])
            ->all();
    }
}
