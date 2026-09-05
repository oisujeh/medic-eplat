<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Patient;
use App\Models\PatientMatchCandidate;
use App\Services\DuplicateReview;
use App\Services\PatientMerger;
use App\Support\PatientOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The duplicate review queue: record pairs the matcher flagged, compared
 * side by side and then merged or dismissed.
 */
class DuplicateController extends Controller
{
    public function __construct(
        private readonly DuplicateReview $review,
        private readonly PatientMerger $merger,
    ) {}

    public function index(Request $request): Response
    {
        $candidates = $this->review->pending()
            ->with(['patient', 'candidate'])
            ->paginate(20)
            ->through(fn (PatientMatchCandidate $c) => [
                'id' => $c->id,
                'score' => $c->score,
                'level' => $c->level->value,
                'level_label' => $c->level->label(),
                'reasons' => $c->signalLabels(),
                'flagged_at' => $c->created_at?->diffForHumans(),
                'a' => $this->summary($c->patient),
                'b' => $this->summary($c->candidate),
                'url' => route('patients.duplicates.show', $c),
            ]);

        return Inertia::render('patients/Duplicates', [
            'candidates' => $candidates,
            'canMerge' => (bool) $request->user()?->hasPermission(Permission::MergePatients),
        ]);
    }

    public function show(Request $request, PatientMatchCandidate $candidate): Response
    {
        $candidate->load(['patient.identifiers', 'patient.mother', 'candidate.identifiers', 'candidate.mother', 'reviewedBy:id,name']);

        return Inertia::render('patients/DuplicateReview', [
            'candidate' => [
                'id' => $candidate->id,
                'score' => $candidate->score,
                'level' => $candidate->level->value,
                'level_label' => $candidate->level->label(),
                'reasons' => $candidate->signalLabels(),
                'status' => $candidate->status->value,
                'status_label' => $candidate->status->label(),
                'reviewed_by' => $candidate->reviewedBy?->name,
                'reviewed_at' => $candidate->reviewed_at?->isoFormat('D MMM YYYY, HH:mm'),
                'notes' => $candidate->notes,
            ],
            'records' => [$this->detail($candidate->patient), $this->detail($candidate->candidate)],
            'canMerge' => (bool) $request->user()?->hasPermission(Permission::MergePatients),
        ]);
    }

    public function dismiss(Request $request, PatientMatchCandidate $candidate): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $this->review->dismiss($candidate, $request->user(), $data['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marked as different people.']);

        return to_route('patients.duplicates.index');
    }

    public function merge(Request $request, PatientMatchCandidate $candidate): RedirectResponse
    {
        $data = $request->validate([
            'survivor_id' => ['required', Rule::in([$candidate->patient_id, $candidate->candidate_id])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $survivor = (int) $data['survivor_id'] === $candidate->patient_id ? $candidate->patient : $candidate->candidate;
        $duplicate = $survivor->is($candidate->patient) ? $candidate->candidate : $candidate->patient;

        $this->merger->merge($survivor, $duplicate, $request->user(), $data['reason'] ?? null);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$duplicate->file_number} merged into {$survivor->file_number}.",
        ]);

        return to_route('patients.show', $survivor);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'file_number' => $patient->file_number,
            'name' => $patient->fullName(),
            'sex' => $patient->sex,
            'age' => $patient->age(),
            'dob' => $patient->date_of_birth?->isoFormat('D MMM YYYY'),
            'phone' => $patient->phone,
            'lga' => $patient->lga,
            'registered_at' => $patient->created_at?->isoFormat('D MMM YYYY'),
            'url' => route('patients.show', $patient),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Patient $patient): array
    {
        $count = fn (string $relation): int => $patient->{$relation}()->count();

        return [
            ...$this->summary($patient),
            'title' => $patient->title,
            'surname' => $patient->surname,
            'first_name' => $patient->first_name,
            'other_names' => $patient->other_names,
            'sex_label' => PatientOptions::SEXES[$patient->sex] ?? $patient->sex,
            'dob_estimated' => $patient->dob_estimated,
            'nin' => $patient->nin,
            'email' => $patient->email,
            'address' => $patient->address,
            'state' => $patient->state,
            'ward' => $patient->ward,
            'next_of_kin' => trim(implode(' · ', array_filter([$patient->next_of_kin_name, $patient->next_of_kin_relationship, $patient->next_of_kin_phone]))) ?: null,
            'coverage' => PatientOptions::COVERAGES[$patient->coverage] ?? $patient->coverage,
            'hmo' => trim(implode(' · ', array_filter([$patient->hmo_name, $patient->hmo_number]))) ?: null,
            'mother' => $patient->mother ? $patient->mother->fullName().' ('.$patient->mother->file_number.')' : null,
            'identifiers' => $patient->identifiers->map(fn ($i) => $i->type->label().': '.$i->value)->values()->all(),
            'activity' => [
                'visits' => $count('visits'),
                'encounters' => $count('encounters'),
                'lab_orders' => $count('labOrders'),
                'medications' => $count('medications'),
                'admissions' => $count('admissions'),
                'bills' => $count('bills'),
                'appointments' => $count('appointments'),
            ],
            'merged' => $patient->merged_into_id !== null,
        ];
    }
}
