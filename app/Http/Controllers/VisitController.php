<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use App\Services\PatientFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VisitController extends Controller
{
    public function __construct(private readonly PatientFlowService $flow) {}

    /**
     * Close an open visit, cancelling any still-active queue entries.
     */
    public function close(Request $request, Visit $visit): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canAccessModule('queues'), 403);

        if ($visit->isOpen()) {
            $this->flow->closeVisit($visit, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visit closed.']);

        return to_route('patients.show', $visit->patient_id);
    }
}
