<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use App\Support\PatientOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PatientRegistrationController extends Controller
{
    /**
     * Show the patient registration form.
     */
    public function create(): Response
    {
        return Inertia::render('patients/Register', [
            'options' => [
                'titles' => PatientOptions::TITLES,
                'sexes' => PatientOptions::SEXES,
                'maritalStatuses' => PatientOptions::MARITAL_STATUSES,
                'nokRelationships' => PatientOptions::NOK_RELATIONSHIPS,
                'coverages' => PatientOptions::COVERAGES,
                'hmoProviders' => PatientOptions::HMO_PROVIDERS,
                'visitCategories' => PatientOptions::VISIT_CATEGORIES,
                'outpatientServices' => PatientOptions::OUTPATIENT_SERVICES,
            ],
            'recentPatients' => Patient::latest()->take(8)->get()->map(fn (Patient $patient) => [
                'file_number' => $patient->file_number,
                'name' => $patient->fullName(),
                'sex' => $patient->sex,
                'age' => $patient->age(),
                'visit_category' => $patient->visit_category,
                'registered_at' => $patient->created_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Store a newly registered patient.
     */
    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = DB::transaction(function () use ($request) {
            $patient = new Patient();
            $patient->fill($request->validated());
            $patient->registered_by = $request->user()->id;
            // file_number is system-generated (not mass-assignable); a temporary
            // unique value satisfies the NOT NULL/unique constraint until the id
            // is known, then it is replaced with the human-readable number.
            $patient->file_number = 'TMP-'.Str::uuid();
            $patient->save();

            $patient->file_number = sprintf('MEP/%d/%06d', $patient->created_at->year, $patient->id);
            $patient->save();

            return $patient;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$patient->fullName()} registered as {$patient->file_number}.",
        ]);

        return to_route('patients.show', $patient);
    }
}
