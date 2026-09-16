<?php

namespace App\Http\Controllers;

use App\Enums\NumberKind;
use App\Events\PatientRegistered;
use App\Events\PatientUpdated;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\Payer;
use App\Services\DomainEvents;
use App\Services\NumberSequences;
use App\Services\PatientIdentifiers;
use App\Support\MatchResult;
use App\Support\PatientOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    public function store(StorePatientRequest $request, NumberSequences $numbers, DomainEvents $events): RedirectResponse
    {
        $patient = DB::transaction(function () use ($request, $numbers, $events) {
            $patient = new Patient;
            $patient->fill($request->validated());
            $this->applyCoverage($patient);
            $patient->registered_by = $request->user()->id;
            // Entered from a paper register: the registration keeps the
            // register's date, not today's.
            $patient->registered_at = $request->filled('registered_at')
                ? Carbon::parse($request->input('registered_at'))
                : now();
            // The file number is issued by the facility's series, not typed.
            $patient->file_number = $numbers->next(NumberKind::Patient);
            $patient->save();

            $events->publish(new PatientRegistered($patient, $request->user()));

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
    /**
     * The Master Patient Index check: people the typed details could already
     * describe, for the registration and edit forms.
     */
    public function lookup(Request $request, PatientIdentifiers $index): JsonResponse
    {
        $except = $request->integer('except') > 0 ? Patient::find($request->integer('except')) : null;

        $candidates = $index->candidates([
            'nin' => $request->string('nin')->toString(),
            'phone' => $request->string('phone')->toString(),
            'surname' => $request->string('surname')->toString(),
            'first_name' => $request->string('first_name')->toString(),
            'date_of_birth' => $request->string('date_of_birth')->toString(),
            'sex' => $request->string('sex')->toString(),
        ], $except);

        return response()->json($candidates->map(fn (MatchResult $match) => [
            'id' => $match->patient->id,
            'file_number' => $match->patient->file_number,
            'name' => $match->patient->fullName(),
            'sex' => $match->patient->sex,
            'age' => $match->patient->age(),
            'phone' => $match->patient->phone,
            'lga' => $match->patient->lga,
            'score' => $match->score,
            'level' => $match->level->value,
            'reasons' => $match->reasons(),
            'strong' => $match->level->isQueued(),
            'url' => route('patients.show', $match->patient),
        ])->values());
    }

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
                'dob_estimated' => $patient->dob_estimated,
                'sex' => $patient->sex,
                'marital_status' => $patient->marital_status ?? '',
                'nin' => $patient->nin ?? '',
                'phone' => $patient->phone ?? '',
                'phone_alternate' => $patient->phone_alternate ?? '',
                'phone_owner' => $patient->phone_owner ?? '',
                'sms_consent' => $patient->sms_consent,
                'is_restricted' => $patient->is_restricted,
                'restricted_reason' => $patient->restricted_reason ?? '',
                'email' => $patient->email ?? '',
                'address' => $patient->address ?? '',
                'nationality' => $patient->nationality ?? '',
                'state' => $patient->state ?? '',
                'lga' => $patient->lga ?? '',
                'ward' => $patient->ward ?? '',
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
    public function update(UpdatePatientRequest $request, Patient $patient, DomainEvents $events): RedirectResponse
    {
        DB::transaction(function () use ($request, $patient, $events) {
            $patient->fill($request->validated());
            $this->applyCoverage($patient);
            $patient->save();

            $changed = array_values(array_diff(array_keys($patient->getChanges()), ['updated_at']));

            if ($changed !== []) {
                $events->publish(new PatientUpdated($patient, $changed, $request->user()));
            }
        });

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
            'phoneOwners' => Patient::PHONE_OWNERS,
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
