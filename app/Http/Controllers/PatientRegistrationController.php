<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\Payer;
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
            'options' => $this->formOptions(),
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
            $patient = new Patient;
            $patient->fill($request->validated());
            $this->applyCoverage($patient);
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

    /**
     * Show the form for revising a patient's demographics and coverage.
     */
    public function edit(Patient $patient): Response
    {
        return Inertia::render('patients/Edit', [
            'options' => $this->formOptions($patient),
            'patient' => [
                'id' => $patient->id,
                'file_number' => $patient->file_number,
                'full_name' => $patient->fullName(),
                'url' => route('patients.show', $patient),
                'title' => $patient->title ?? '',
                'surname' => $patient->surname,
                'first_name' => $patient->first_name,
                'other_names' => $patient->other_names ?? '',
                'date_of_birth' => $patient->date_of_birth?->toDateString() ?? '',
                'sex' => $patient->sex,
                'marital_status' => $patient->marital_status ?? '',
                'phone' => $patient->phone ?? '',
                'email' => $patient->email ?? '',
                'address' => $patient->address ?? '',
                'nationality' => $patient->nationality ?? '',
                'state' => $patient->state ?? '',
                'lga' => $patient->lga ?? '',
                'next_of_kin_name' => $patient->next_of_kin_name ?? '',
                'next_of_kin_relationship' => $patient->next_of_kin_relationship ?? '',
                'next_of_kin_phone' => $patient->next_of_kin_phone ?? '',
                'coverage' => $patient->coverage,
                'payer_id' => $patient->payer_id ? (string) $patient->payer_id : '',
                'hmo_name' => $patient->hmo_name ?? '',
                'hmo_number' => $patient->hmo_number ?? '',
                'hmo_plan' => $patient->hmo_plan ?? '',
                'hmo_expires_at' => $patient->hmo_expires_at?->toDateString() ?? '',
                'is_transfer' => $patient->is_transfer,
                'transfer_from' => $patient->transfer_from ?? '',
                'transfer_reason' => $patient->transfer_reason ?? '',
                'transfer_service' => $patient->transfer_service ?? '',
                'visit_category' => $patient->visit_category,
                'outpatient_service' => $patient->outpatient_service ?? '',
            ],
        ]);
    }

    /**
     * Save changes to a patient's demographics and coverage.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->fill($request->validated());
        $this->applyCoverage($patient);
        $patient->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$patient->fullName()}'s record updated."]);

        return to_route('patients.show', $patient);
    }

    /**
     * Keep the provider name and payer link consistent with the coverage.
     * The provider name follows the payer on record, so claims and the
     * profile always agree; a private patient carries no payer details.
     */
    private function applyCoverage(Patient $patient): void
    {
        if ($patient->coverage !== 'hmo') {
            $patient->payer_id = null;
            $patient->hmo_name = null;
            $patient->hmo_number = null;
            $patient->hmo_plan = null;
            $patient->hmo_expires_at = null;

            return;
        }

        if ($patient->payer_id) {
            $patient->hmo_name = Payer::find($patient->payer_id)?->name ?? $patient->hmo_name;
        }
    }

    /**
     * The pick-lists behind the registration and edit forms. A patient's
     * current payer stays selectable even if it has since been deactivated.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Patient $patient = null): array
    {
        $payers = Payer::query()
            ->where(fn ($q) => $q->where('is_active', true)
                ->when($patient?->payer_id, fn ($w) => $w->orWhere('id', $patient->payer_id)))
            ->orderBy('name')
            ->get();

        return [
            'titles' => PatientOptions::TITLES,
            'sexes' => PatientOptions::SEXES,
            'maritalStatuses' => PatientOptions::MARITAL_STATUSES,
            'nokRelationships' => PatientOptions::NOK_RELATIONSHIPS,
            'coverages' => PatientOptions::COVERAGES,
            'hmoProviders' => PatientOptions::HMO_PROVIDERS,
            'payers' => $payers->map(fn (Payer $payer) => [
                'id' => $payer->id,
                'name' => $payer->name,
                'type_label' => $payer->type->label(),
            ]),
            'visitCategories' => PatientOptions::VISIT_CATEGORIES,
            'outpatientServices' => PatientOptions::OUTPATIENT_SERVICES,
        ];
    }
}
