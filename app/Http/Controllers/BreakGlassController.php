<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\RestrictedRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Opening a restricted patient record with a logged reason.
 */
class BreakGlassController extends Controller
{
    public function __construct(private readonly RestrictedRecords $restricted) {}

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->isRestricted(), 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Give the reason you need to open this record. It is written to the audit trail.',
            'reason.min' => 'Give a fuller reason; it is what the facility admin will review.',
        ]);

        $this->restricted->breakGlass($patient, $request->user(), $request->session(), $data['reason']);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Restricted record opened. Your reason has been logged.']);

        return to_route('patients.show', $patient);
    }
}
