<?php

namespace App\Http\Controllers;

use App\Enums\RelationshipType;
use App\Models\Patient;
use App\Models\PatientRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Linking patients on the register to one another (PRD REG-18): mother and
 * child, household members, an index case and its contacts.
 */
class PatientRelationshipController extends Controller
{
    /**
     * Patients a records officer could link to, by name or file number.
     */
    public function search(Request $request, Patient $patient): JsonResponse
    {
        $q = trim($request->string('q')->value());

        $patients = Patient::query()
            ->current()
            ->whereKeyNot($patient->id)
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('file_number', 'like', "%{$q}%")
                ->orWhere('surname', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('other_names', 'like', "%{$q}%")))
            ->orderBy('surname')
            ->limit(10)
            ->get()
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'name' => $p->fullName(),
                'file_number' => $p->file_number,
                'sex' => $p->sex,
                'age' => $p->age(),
            ]);

        return response()->json(['patients' => $patients]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'related_patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('merged_into_id'), Rule::notIn([$patient->id])],
            'type' => ['required', Rule::enum(RelationshipType::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'related_patient_id.not_in' => 'A patient cannot be linked to themselves.',
        ]);

        $related = Patient::query()->findOrFail((int) $data['related_patient_id']);
        $type = RelationshipType::from($data['type']);

        PatientRelationship::link($patient, $related, $type, $request->user(), $data['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$related->fullName()} linked as {$type->label()}."]);

        return back();
    }

    /**
     * Remove a link and its mirror on the other patient.
     */
    public function destroy(Patient $patient, PatientRelationship $relationship): RedirectResponse
    {
        abort_unless($relationship->patient_id === $patient->id, 404);

        DB::transaction(function () use ($relationship) {
            PatientRelationship::query()
                ->where('patient_id', $relationship->related_patient_id)
                ->where('related_patient_id', $relationship->patient_id)
                ->where('type', $relationship->type->inverse()->value)
                ->get()
                ->each(fn (PatientRelationship $mirror) => $mirror->delete());

            $relationship->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link removed.']);

        return back();
    }
}
