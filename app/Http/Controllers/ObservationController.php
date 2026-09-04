<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreObservationsRequest;
use App\Models\Patient;
use App\Services\ObservationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Measurements recorded for a patient — from a queue, an encounter or a ward.
 */
class ObservationController extends Controller
{
    public function __construct(private readonly ObservationService $observations) {}

    /**
     * Record a set of readings.
     */
    public function store(StoreObservationsRequest $request, Patient $patient): RedirectResponse
    {
        $this->observations->record(
            patient: $patient,
            actor: $request->user(),
            values: $request->readings(),
            notes: $request->validated('notes'),
            queueEntry: $request->queueEntry(),
            encounter: $request->encounter(),
            admission: $request->admission(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Observations recorded.']);

        return back();
    }
}
