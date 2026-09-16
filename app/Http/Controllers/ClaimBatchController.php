<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\ClaimBatch;
use App\Services\ClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly claims schedules, one per payer.
 */
class ClaimBatchController extends Controller
{
    public function __construct(private readonly ClaimService $claims) {}

    /**
     * Every schedule, newest period first.
     */
    public function index(): Response
    {
        $batches = ClaimBatch::query()
            ->with(['payer:id,name,code', 'submittedBy:id,name'])
            ->withCount('claims')
            ->withSum('claims as payer_amount', 'payer_amount')
            ->withSum('claims as paid_amount', 'paid_amount')
            ->orderByDesc('period')
            ->orderBy('payer_id')
            ->get();

        return Inertia::render('claims/Batches', [
            'batches' => $batches->map(fn (ClaimBatch $batch) => $this->card($batch)),
        ]);
    }

    /**
     * A schedule and the claims in it, laid out for printing.
     */
    public function show(ClaimBatch $batch): Response
    {
        $batch->load([
            'payer:id,name,code,type',
            'submittedBy:id,name',
            'claims.patient:id,file_number,surname,first_name,other_names',
        ])->loadCount('claims')
            ->loadSum('claims as payer_amount', 'payer_amount')
            ->loadSum('claims as paid_amount', 'paid_amount');

        return Inertia::render('claims/Batch', [
            'batch' => $this->card($batch),
            'claims' => $batch->claims->map(fn (Claim $claim) => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'patient' => $claim->patient->fullName(),
                'file_number' => $claim->patient->file_number,
                'enrollee_number' => $claim->enrollee_number,
                'service_date' => $claim->service_date->isoFormat('D MMM YYYY'),
                'diagnosis' => $claim->diagnosis,
                'authorization_code' => $claim->authorization_code,
                'gross_amount' => $claim->gross_amount,
                'copay_amount' => $claim->copay_amount,
                'payer_amount' => $claim->payer_amount,
                'paid_amount' => $claim->paid_amount,
                'status' => $claim->status->value,
                'status_label' => $claim->status->label(),
                'tone' => $claim->status->tone(),
                'url' => route('claims.show', $claim),
            ]),
        ]);
    }

    /**
     * Send the schedule to the payer.
     */
    public function submit(Request $request, ClaimBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->claims->submitBatch($batch, $data['reference'] ?? null, $request->user(), $data['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Schedule {$batch->batch_number} submitted."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function card(ClaimBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'payer' => $batch->payer->name,
            'payer_code' => $batch->payer->code,
            'period' => $batch->period,
            'period_label' => $batch->periodLabel(),
            'status' => $batch->status->value,
            'status_label' => $batch->status->label(),
            'tone' => $batch->status->tone(),
            'claims_count' => (int) $batch->claims_count,
            'payer_amount' => (float) ($batch->payer_amount ?? 0),
            'paid_amount' => (float) ($batch->paid_amount ?? 0),
            'submitted_at' => $batch->submitted_at?->isoFormat('D MMM YYYY'),
            'submitted_by' => $batch->submittedBy?->name,
            'reference' => $batch->reference,
            'notes' => $batch->notes,
            'url' => route('claims.batches.show', $batch),
        ];
    }
}
