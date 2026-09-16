<?php

namespace App\Http\Controllers;

use App\Enums\PatientStatus;
use App\Http\Requests\UpdatePatientStatusRequest;
use App\Models\Patient;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Moving a patient between active, transferred out and lost to follow-up
 * (PRD REG-19). Each change is dated on the record and carries its reason
 * into the audit trail.
 */
class PatientStatusController extends Controller
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function update(UpdatePatientStatusRequest $request, Patient $patient): RedirectResponse
    {
        if ($patient->status === PatientStatus::Deceased) {
            throw ValidationException::withMessages(['status' => 'A deceased patient\'s status cannot be changed.']);
        }

        $data = $request->validated();
        $status = PatientStatus::from($data['status']);

        $this->audit->withReason($data['reason'], fn () => $patient->changeStatus(
            $status,
            $data['reason'],
            $data['transferred_to'] ?? null,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$patient->fullName()} is now {$status->label()}."]);

        return back();
    }
}
