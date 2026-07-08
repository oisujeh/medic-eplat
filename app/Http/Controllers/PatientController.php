<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\Visit;
use App\Support\PatientOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    /**
     * Display a searchable, paginated list of patients.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $patients = Patient::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('surname', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('other_names', 'like', "%{$search}%")
                        ->orWhere('file_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Patient $patient) => [
                'id' => $patient->id,
                'file_number' => $patient->file_number,
                'name' => $patient->fullName(),
                'initials' => $patient->initials(),
                'sex' => $patient->sex,
                'age' => $patient->age(),
                'phone' => $patient->phone,
                'state' => $patient->state,
                'lga' => $patient->lga,
                'coverage' => $patient->coverage,
                'visit_category' => $patient->visit_category,
                'registered_at' => $patient->created_at?->diffForHumans(),
                'url' => route('patients.show', $patient),
            ]);

        return Inertia::render('patients/Index', [
            'patients' => $patients,
            'filters' => ['search' => $search],
            'canRegister' => (bool) $request->user()?->canAccessModule('registration'),
        ]);
    }

    /**
     * Display a single patient's profile.
     */
    public function show(Request $request, Patient $patient): Response
    {
        $patient->load('registeredBy:id,name');

        return Inertia::render('patients/Show', [
            'patient' => [
                'id' => $patient->id,
                'file_number' => $patient->file_number,
                'title' => $patient->title,
                'full_name' => $patient->fullName(),
                'initials' => $patient->initials(),
                'surname' => $patient->surname,
                'first_name' => $patient->first_name,
                'other_names' => $patient->other_names,
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
                'date_of_birth_label' => $patient->date_of_birth?->isoFormat('D MMM YYYY'),
                'age' => $patient->age(),
                'sex' => $patient->sex,
                'sex_label' => PatientOptions::SEXES[$patient->sex] ?? $patient->sex,
                'marital_status' => $patient->marital_status,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'address' => $patient->address,
                'nationality' => $patient->nationality,
                'state' => $patient->state,
                'lga' => $patient->lga,
                'next_of_kin_name' => $patient->next_of_kin_name,
                'next_of_kin_relationship' => $patient->next_of_kin_relationship,
                'next_of_kin_phone' => $patient->next_of_kin_phone,
                'coverage' => $patient->coverage,
                'coverage_label' => PatientOptions::COVERAGES[$patient->coverage] ?? $patient->coverage,
                'hmo_name' => $patient->hmo_name,
                'hmo_number' => $patient->hmo_number,
                'is_transfer' => $patient->is_transfer,
                'transfer_from' => $patient->transfer_from,
                'transfer_reason' => $patient->transfer_reason,
                'transfer_service' => $patient->transfer_service,
                'visit_category' => $patient->visit_category,
                'outpatient_service' => $patient->outpatient_service,
                'registered_by' => $patient->registeredBy?->name,
                'registered_at' => $patient->created_at?->isoFormat('D MMM YYYY, h:mm a'),
                'registered_at_diff' => $patient->created_at?->diffForHumans(),
            ],
            'openVisit' => $this->presentOpenVisit($patient),
            'vitals' => $patient->vitalSigns()->with('recordedBy:id,name')->take(10)->get()
                ->map(fn ($vital) => $vital->summary()),
            'encounters' => $patient->encounters()->with('clinician:id,name')->take(20)->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'presenting_complaint' => $e->presenting_complaint,
                    'history' => $e->history,
                    'examination' => $e->examination,
                    'diagnosis' => $e->diagnosis,
                    'plan' => $e->plan,
                    'status' => $e->status,
                    'clinician' => $e->clinician?->name,
                    'date' => ($e->completed_at ?? $e->created_at)?->isoFormat('D MMM YYYY, h:mm a'),
                ]),
            'servicePoints' => ServicePoint::active()->get()
                ->map(fn (ServicePoint $sp) => [
                    'id' => $sp->id,
                    'name' => $sp->name,
                    'personnel' => $sp->eligiblePersonnel()
                        ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                        ->values(),
                ]),
            'routeOptions' => [
                'priorities' => collect(Priority::cases())->map(fn (Priority $p) => [
                    'value' => $p->value,
                    'label' => $p->label(),
                ]),
                'visitReasons' => PatientOptions::VISIT_REASONS,
            ],
            'canRoute' => (bool) $request->user()?->canAccessModule('queues'),
        ]);
    }

    /**
     * Present the patient's open visit and its queue timeline, if any.
     *
     * @return array<string, mixed>|null
     */
    private function presentOpenVisit(Patient $patient): ?array
    {
        $visit = $patient->openVisit();

        if (! $visit) {
            return null;
        }

        $visit->load([
            'queueEntries.servicePoint:id,name',
            'queueEntries.assignedTo:id,name',
            'queueEntries.routedBy:id,name',
        ]);

        return [
            'id' => $visit->id,
            'visit_number' => $visit->visit_number,
            'reason' => $visit->reason,
            'opened_at' => $visit->opened_at?->isoFormat('D MMM YYYY, h:mm a'),
            'opened_at_diff' => $visit->opened_at?->diffForHumans(),
            'entries' => $visit->queueEntries->map(fn (QueueEntry $entry) => [
                'id' => $entry->id,
                'service_point' => $entry->servicePoint->name,
                'status' => $entry->status->value,
                'status_label' => $entry->status->label(),
                'priority' => $entry->priority->value,
                'priority_label' => $entry->priority->label(),
                'note' => $entry->note,
                'assigned_to' => $entry->assignedTo?->name,
                'routed_by' => $entry->routedBy?->name,
                'queued_at' => $entry->queued_at?->isoFormat('h:mm a'),
                'started_at' => $entry->started_at?->isoFormat('h:mm a'),
                'completed_at' => $entry->completed_at?->isoFormat('h:mm a'),
            ]),
        ];
    }
}
