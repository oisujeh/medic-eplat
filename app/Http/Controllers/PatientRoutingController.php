<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Http\Requests\RoutePatientRequest;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\PatientFlowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PatientRoutingController extends Controller
{
    public function __construct(private readonly PatientFlowService $flow) {}

    /**
     * Route a patient to a service point (placing them in its queue).
     */
    public function store(RoutePatientRequest $request, Patient $patient): RedirectResponse
    {
        $servicePoint = ServicePoint::findOrFail($request->integer('service_point_id'));
        $assignee = $this->resolveAssignee($request->input('assigned_to'), $servicePoint);

        $this->flow->route(
            patient: $patient,
            servicePoint: $servicePoint,
            actor: $request->user(),
            priority: Priority::from($request->string('priority')),
            note: $request->input('note'),
            visitReason: $request->input('visit_reason'),
            assignedTo: $assignee,
        );

        $suffix = $assignee ? " ({$assignee->name})" : '';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$patient->fullName()} sent to {$servicePoint->name}{$suffix}.",
        ]);

        return to_route('patients.show', $patient);
    }

    /**
     * Resolve an assignee id to a user, but only if they are eligible to work
     * the target service point. Otherwise the entry is left for the shared pool.
     */
    private function resolveAssignee(mixed $userId, ServicePoint $servicePoint): ?User
    {
        if (! $userId) {
            return null;
        }

        return $servicePoint->eligiblePersonnel()->firstWhere('id', (int) $userId);
    }
}
