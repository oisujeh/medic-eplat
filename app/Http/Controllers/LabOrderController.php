<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Http\Controllers\Concerns\AssertsEncounterRecord;
use App\Http\Requests\RecordLabResultRequest;
use App\Http\Requests\StoreLabOrderRequest;
use App\Models\Encounter;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Services\LabWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Laboratory requisitions placed from within an encounter.
 */
class LabOrderController extends Controller
{
    use AssertsEncounterRecord;

    public function __construct(private readonly LabWorkflowService $lab) {}

    /**
     * Place a requisition from catalogue tests and/or a free-text entry. The
     * order flows into the laboratory worklist for processing.
     */
    public function store(StoreLabOrderRequest $request, Encounter $encounter): RedirectResponse
    {
        $data = $request->validated();

        $tests = LabTest::whereIn('id', $data['lab_test_ids'] ?? [])->get();
        $adHoc = filled($data['name'] ?? null)
            ? [['name' => $data['name'], 'specimen' => $data['specimen'] ?? null]]
            : [];

        $order = $this->lab->createOrder(
            patient: $encounter->patient,
            orderedBy: $request->user(),
            tests: $tests,
            adHoc: $adHoc,
            priority: Priority::from($data['priority'] ?? Priority::Normal->value),
            clinicalDetails: $data['clinical_details'] ?? null,
            visit: $encounter->visit,
            encounter: $encounter,
            queueEntry: $encounter->queueEntry,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lab requisition {$order->accession_number} placed."]);

        return back();
    }

    /**
     * Record the result for a pending order line.
     */
    public function result(RecordLabResultRequest $request, Encounter $encounter, LabResult $labResult): RedirectResponse
    {
        $this->assertBelongsToPatient($encounter, $labResult);

        $labResult->update([
            ...$request->validated(),
            'status' => LabResult::STATUS_RESULTED,
            'resulted_by' => $request->user()->id,
            'resulted_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Result recorded.']);

        return back();
    }

    /**
     * Remove an order line from the patient's record.
     */
    public function destroy(Request $request, Encounter $encounter, LabResult $labResult): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        $this->assertBelongsToPatient($encounter, $labResult);

        $labResult->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lab order removed.']);

        return back();
    }
}
