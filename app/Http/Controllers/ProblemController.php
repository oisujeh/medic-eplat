<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\ProblemRequest;
use App\Models\Encounter;
use App\Models\IcdCode;
use App\Models\Problem;
use App\Models\SurveillanceCase;
use App\Services\CaseSurveillance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The patient's problem list, maintained from within an encounter.
 */
class ProblemController extends Controller
{
    use AssertsEncounterRecord;

    public function __construct(private readonly CaseSurveillance $surveillance) {}

    /**
     * Add a condition (or coded diagnosis) to the patient's problem list.
     */
    public function store(ProblemRequest $request, Encounter $encounter): RedirectResponse
    {
        $problem = $encounter->patient->problems()->create([
            ...$this->withIcdCode($request->validated()),
            'encounter_id' => $encounter->id,
            'recorded_by' => $request->user()->id,
        ]);

        $case = $this->surveillance->screen($problem, $request->user());

        $this->flash($case, 'Added to problem list.');

        return back();
    }

    /**
     * Update an existing problem on the patient's list.
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

        $case = $this->surveillance->screen($problem, $request->user());

        $this->flash($case, 'Problem updated.');

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

        $this->surveillance->forget($problem);
        $problem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Problem removed.']);

        return back();
    }

    /**
     * The usual success toast, replaced by an IDSR warning when this write is
     * what detected a notifiable disease.
     */
    private function flash(?SurveillanceCase $case, string $message): void
    {
        if ($case?->wasRecentlyCreated) {
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
     * Normalise a typed ICD code and link it to the catalogue when it exists.
     * A code outside the catalogue is kept as typed, unlinked.
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
