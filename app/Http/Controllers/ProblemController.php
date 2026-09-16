<?php

namespace App\Http\Controllers;

use App\Events\DiagnosisRecorded;
use App\Events\DiagnosisRemoved;
use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\ProblemRequest;
use App\Models\Encounter;
use App\Models\IcdCode;
use App\Models\Problem;
use App\Services\CaseSurveillance;
use App\Services\DomainEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProblemController extends Controller
{
    use AssertsEncounterRecord;

    public function __construct(
        private readonly DomainEvents $events,
        private readonly CaseSurveillance $surveillance,
    ) {}

    /**
     * Add a problem to the patient's list from the encounter.
     */
    public function store(ProblemRequest $request, Encounter $encounter): RedirectResponse
    {
        $problem = $encounter->patient->problems()->create([
            ...$this->withIcdCode($request->validated()),
            'encounter_id' => $encounter->id,
            'recorded_by' => $request->user()->id,
        ]);

        $this->events->publish(new DiagnosisRecorded($problem, $request->user()));
        $this->flash($problem, 'Added to problem list.');

        return back();
    }

    /**
     * Update a problem on the patient's list.
     */
    public function update(ProblemRequest $request, Encounter $encounter, Problem $problem): RedirectResponse
    {
        $this->assertBelongsToPatient($encounter, $problem);

        $data = $request->validated();

        // Keep the resolved date consistent with the chosen status.
        $data['resolved_date'] = $data['status'] === Problem::STATUS_RESOLVED
            ? ($problem->resolved_date ?? now())
            : null;

        $problem->update($this->withIcdCode($data));

        $this->events->publish(new DiagnosisRecorded($problem, $request->user()));
        $this->flash($problem, 'Problem updated.');

        return back();
    }

    /**
     * Mark a problem as resolved.
     */
    public function resolve(Request $request, Encounter $encounter, Problem $problem): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        $this->assertBelongsToPatient($encounter, $problem);

        $problem->update(['status' => Problem::STATUS_RESOLVED, 'resolved_date' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Problem marked resolved.']);

        return back();
    }

    /**
     * Remove a problem from the patient's list.
     */
    public function destroy(Request $request, Encounter $encounter, Problem $problem): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        $this->assertBelongsToPatient($encounter, $problem);

        $this->events->publish(new DiagnosisRemoved($problem, $request->user()));
        $problem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Problem removed.']);

        return back();
    }

    /**
     * Confirm the save, or warn instead while the diagnosis has an IDSR case
     * the DSNO has not yet been told about.
     */
    private function flash(Problem $problem, string $message): void
    {
        $case = $this->surveillance->caseFor($problem);

        if ($case !== null && $case->classification->isOpen() && $case->notified_at === null) {
            $disease = $case->disease;

            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => "{$disease->name} is {$disease->category->label()} under IDSR. {$disease->category->instruction()}",
            ]);

            return;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    /**
     * Resolve the typed ICD-10 code against the catalogue, keeping the
     * normalised code even when it is not catalogued.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withIcdCode(array $data): array
    {
        $code = trim((string) ($data['code'] ?? ''));

        if ($code === '') {
            return [...$data, 'code' => null, 'icd_code_id' => null];
        }

        $match = IcdCode::findByCode($code);

        return [
            ...$data,
            'code' => $match->code ?? IcdCode::normalise($code),
            'icd_code_id' => $match?->id,
        ];
    }
}
