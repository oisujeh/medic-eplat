<?php

namespace App\Services;

use App\Enums\LabOrderStatus;
use App\Enums\NumberKind;
use App\Enums\Priority;
use App\Events\LabResultVerified;
use App\Models\BillCharge;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Drives the laboratory requisition lifecycle: placing an order (expanding
 * panels into result lines), specimen collection and receipt, result entry
 * with auto-flagging, and verification/release back to the chart.
 */
class LabWorkflowService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly NumberSequences $numbers,
        private readonly DomainEvents $events,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * Place a requisition for a patient. Catalog panels are expanded into their
     * component analytes; ad-hoc entries create free-text result lines.
     *
     * @param  Collection<int, LabTest>  $tests  catalog tests / panels chosen
     * @param  array<int, array{name: string, specimen?: string|null, unit?: string|null}>  $adHoc
     */
    public function createOrder(
        Patient $patient,
        User $orderedBy,
        Collection $tests,
        array $adHoc = [],
        Priority $priority = Priority::Normal,
        ?string $clinicalDetails = null,
        ?Visit $visit = null,
        ?Encounter $encounter = null,
        ?QueueEntry $queueEntry = null,
    ): LabOrder {
        return DB::transaction(function () use ($patient, $orderedBy, $tests, $adHoc, $priority, $clinicalDetails, $visit, $encounter, $queueEntry) {
            $order = LabOrder::create([
                'accession_number' => $this->numbers->next(NumberKind::LabOrder),
                'patient_id' => $patient->id,
                'visit_id' => $visit?->id,
                'encounter_id' => $encounter?->id,
                'queue_entry_id' => $queueEntry?->id,
                'ordered_by' => $orderedBy->id,
                'priority' => $priority,
                'status' => LabOrderStatus::Ordered,
                'clinical_details' => $clinicalDetails,
            ]);

            // Expand catalog tests (panels → components) into result lines.
            foreach ($tests as $test) {
                foreach ($test->resultableTests() as $analyte) {
                    $this->addResultLine($order, $patient, $orderedBy, $analyte);
                }
            }

            foreach ($adHoc as $line) {
                $order->results()->create([
                    'patient_id' => $patient->id,
                    'visit_id' => $visit?->id,
                    'encounter_id' => $encounter?->id,
                    'ordered_by' => $orderedBy->id,
                    'name' => $line['name'],
                    'specimen' => $line['specimen'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'status' => LabResult::STATUS_PENDING,
                ]);
            }

            // Default the order's specimen type from its first line.
            $order->update([
                'specimen_type' => $order->results()->whereNotNull('specimen')->value('specimen'),
            ]);

            return $order->refresh();
        });
    }

    /**
     * Record specimen collection, moving the order into the lab pipeline.
     */
    public function collect(LabOrder $order, User $actor, ?string $specimenType = null): LabOrder
    {
        $order->update([
            'status' => LabOrderStatus::Collected,
            'specimen_type' => $specimenType ?: $order->specimen_type,
            'collected_by' => $actor->id,
            'collected_at' => now(),
        ]);

        return $order;
    }

    /**
     * Mark the specimen received at the bench and open it for analysis.
     */
    public function receive(LabOrder $order, User $actor): LabOrder
    {
        $order->update([
            'status' => LabOrderStatus::InProgress,
            'received_by' => $actor->id,
            'received_at' => now(),
        ]);

        return $order;
    }

    /**
     * Enter/update result values. Values are held as preliminary (the lines
     * stay pending) until the order is verified and released.
     *
     * @param  array<int, array{value?: string|null, flag?: string|null, notes?: string|null}>  $entries  keyed by result id
     */
    public function recordResults(LabOrder $order, array $entries): LabOrder
    {
        return DB::transaction(function () use ($order, $entries) {
            foreach ($order->results as $result) {
                if (! array_key_exists($result->id, $entries)) {
                    continue;
                }

                $entry = $entries[$result->id];
                $result->value = $entry['value'] ?? null;
                $result->notes = $entry['notes'] ?? $result->notes;

                // Prefer an explicit flag; otherwise derive Low/High from the range.
                $result->flag = $entry['flag'] ?? $result->deriveFlag()?->value;
                $result->save();
            }

            return $order->refresh();
        });
    }

    /**
     * Verify and release the order: every value is finalised, the lines become
     * resulted (visible in the chart), and the order is completed.
     */
    public function verify(LabOrder $order, User $actor): LabOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            foreach ($order->results as $result) {
                $result->update([
                    'status' => LabResult::STATUS_RESULTED,
                    'resulted_by' => $result->resulted_by ?? $actor->id,
                    'resulted_at' => $result->resulted_at ?? now(),
                ]);

                $this->events->publish(new LabResultVerified($result, $actor));
            }

            $order->update([
                'status' => LabOrderStatus::Completed,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);

            $this->postCharges($order, $actor);

            return $order->refresh();
        });
    }

    /**
     * Post each priced test on a released order to the patient's running bill.
     */
    private function postCharges(LabOrder $order, User $actor): void
    {
        if (! $order->visit_id) {
            return;
        }

        $order->loadMissing(['patient', 'visit', 'results.test']);
        $bill = null;

        foreach ($order->results as $result) {
            $price = $result->test?->price;

            if (! $price || $price <= 0) {
                continue;
            }

            $bill ??= $this->billing->openBillFor($order->patient, $order->visit);
            $this->billing->postCharge(
                bill: $bill,
                source: BillCharge::SOURCE_LABORATORY,
                description: $result->name,
                quantity: 1,
                unitPrice: (float) $price,
                actor: $actor,
                reference: $result,
            );
        }
    }

    /**
     * Cancel an active order.
     */
    public function cancel(LabOrder $order, User $actor, ?string $reason = null): LabOrder
    {
        $this->audit->withReason($reason, fn () => $order->update([
            'status' => LabOrderStatus::Cancelled,
            'cancelled_reason' => $reason,
        ]));

        return $order;
    }

    /**
     * Create a pending result line from a catalog analyte, copying its metadata.
     */
    private function addResultLine(LabOrder $order, Patient $patient, User $orderedBy, LabTest $analyte): void
    {
        // The limits for this patient's age and sex are copied onto the line,
        // so a later change to the catalogue never re-flags an old result.
        $range = $analyte->rangeFor(
            $patient->date_of_birth?->diffInDays(now(), absolute: true) !== null
                ? (int) $patient->date_of_birth->diffInDays(now(), absolute: true)
                : null,
            $patient->sex,
        );

        $order->results()->create([
            'lab_test_id' => $analyte->id,
            'catalogue_version_id' => $analyte->currentVersion()->id,
            'patient_id' => $patient->id,
            'visit_id' => $order->visit_id,
            'encounter_id' => $order->encounter_id,
            'ordered_by' => $orderedBy->id,
            'name' => $analyte->name,
            'code' => $analyte->code,
            'department' => $analyte->department->value,
            'unit' => $analyte->unit,
            'reference_range' => $this->referenceLabel($range, $analyte),
            'reference_low' => $range['low'],
            'reference_high' => $range['high'],
            'critical_low' => $range['critical_low'],
            'critical_high' => $range['critical_high'],
            'specimen' => $analyte->specimen_type,
            'status' => LabResult::STATUS_PENDING,
        ]);
    }

    /**
     * The printed form of the limits that apply, e.g. "11.5 – 16.5 g/dL".
     *
     * @param  array{low: float|null, high: float|null, critical_low: float|null, critical_high: float|null, text: string|null}  $range
     */
    private function referenceLabel(array $range, LabTest $analyte): ?string
    {
        if ($range['low'] === null && $range['high'] === null) {
            return $range['text'] ?? $analyte->referenceLabel();
        }

        $unit = $analyte->unit ? " {$analyte->unit}" : '';

        return match (true) {
            $range['low'] !== null && $range['high'] !== null => rtrim(rtrim(number_format($range['low'], 3, '.', ''), '0'), '.').' – '.rtrim(rtrim(number_format($range['high'], 3, '.', ''), '0'), '.').$unit,
            $range['low'] !== null => '≥ '.rtrim(rtrim(number_format($range['low'], 3, '.', ''), '0'), '.').$unit,
            default => '≤ '.rtrim(rtrim(number_format((float) $range['high'], 3, '.', ''), '0'), '.').$unit,
        };
    }
}
