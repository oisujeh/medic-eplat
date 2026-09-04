<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\BillStatus;
use App\Enums\LabOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\QueueStatus;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Dispense;
use App\Models\DispenseItem;
use App\Models\Encounter;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates operational and financial data into the figures that back the
 * Reports dashboard. All date-bucketing is done in PHP so the queries stay
 * portable across the SQLite (dev/test) and MySQL (production) drivers.
 */
class ReportService
{
    /**
     * Build the full reporting payload for a date range.
     *
     * @return array<string, mixed>
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return [
            'kpis' => $this->kpis($from, $to),
            'revenueTrend' => $this->revenueTrend($from, $to),
            'visitsTrend' => $this->visitsTrend($from, $to),
            'revenueByMethod' => $this->revenueByMethod($from, $to),
            'appointmentsByStatus' => $this->appointmentsByStatus($from, $to),
            'labByStatus' => $this->labByStatus($from, $to),
            'servicePointThroughput' => $this->servicePointThroughput($from, $to),
            'patientFlow' => $this->patientFlow($from, $to),
            'topDiagnoses' => $this->topDiagnoses($from, $to),
            'topDispensed' => $this->topDispensed($from, $to),
            'lowStock' => $this->lowStock(),
        ];
    }

    /**
     * Completed queue throughput per service point, busiest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function servicePointThroughput(Carbon $from, Carbon $to): array
    {
        $counts = QueueEntry::query()
            ->where('status', QueueStatus::Completed->value)
            ->whereBetween('completed_at', [$from, $to])
            ->get(['service_point_id'])
            ->groupBy('service_point_id')
            ->map->count();

        $names = ServicePoint::query()->pluck('name', 'id');

        return $counts
            ->map(fn (int $count, int|string $id) => [
                'label' => $names[$id] ?? 'Unknown',
                'value' => $count,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Queue hops in the range by outcome: completed, still active, cancelled.
     *
     * @return array<int, array<string, mixed>>
     */
    private function patientFlow(Carbon $from, Carbon $to): array
    {
        $entries = QueueEntry::query()->whereBetween('queued_at', [$from, $to])->get(['status']);
        $active = $entries->whereIn('status', [QueueStatus::Waiting, QueueStatus::InService])->count();

        return collect([
            ['label' => 'Completed', 'value' => $entries->where('status', QueueStatus::Completed)->count()],
            ['label' => 'In progress', 'value' => $active],
            ['label' => 'Cancelled', 'value' => $entries->where('status', QueueStatus::Cancelled)->count()],
        ])->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /**
     * The most frequent working diagnoses recorded in the range.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topDiagnoses(Carbon $from, Carbon $to): array
    {
        return Encounter::consultations()->signed()
            ->whereBetween('signed_at', [$from, $to])
            ->whereNotNull('assessment')
            ->where('assessment', '!=', '')
            ->pluck('assessment')
            ->map(fn (string $d) => trim($d))
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $name) => ['label' => $name, 'value' => $count])
            ->values()
            ->all();
    }

    /**
     * Headline metrics, each with the equivalent figure for the immediately
     * preceding period of equal length so a trend delta can be shown.
     *
     * @return array<string, array<string, mixed>>
     */
    private function kpis(Carbon $from, Carbon $to): array
    {
        $length = $from->diffInSeconds($to);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subSeconds($length);

        $metric = fn (callable $q): array => [
            'value' => $q($from, $to),
            'previous' => $q($prevFrom, $prevTo),
        ];

        return [
            'revenue' => $metric(fn ($a, $b) => (float) Payment::whereBetween('created_at', [$a, $b])->sum('amount')),
            'patients' => $metric(fn ($a, $b) => Patient::whereBetween('created_at', [$a, $b])->count()),
            'visits' => $metric(fn ($a, $b) => Visit::whereBetween('opened_at', [$a, $b])->count()),
            'consultations' => $metric(fn ($a, $b) => Encounter::consultations()->signed()
                ->whereBetween('signed_at', [$a, $b])->count()),
            'labOrders' => $metric(fn ($a, $b) => LabOrder::whereBetween('created_at', [$a, $b])->count()),
            'prescriptions' => $metric(fn ($a, $b) => Dispense::where('status', Dispense::STATUS_DISPENSED)
                ->whereBetween('created_at', [$a, $b])->count()),
            'appointments' => $metric(fn ($a, $b) => Appointment::whereBetween('scheduled_start', [$a, $b])->count()),
            'outstanding' => [
                // Current snapshot of money owed — not period-bound.
                'value' => $this->outstandingBalance(),
                'previous' => null,
            ],
        ];
    }

    /**
     * Total unpaid balance across all bills still accruing.
     */
    public function outstandingBalance(): float
    {
        $charges = Bill::query()
            ->whereIn('status', [BillStatus::Open->value, BillStatus::PartiallyPaid->value])
            ->withSum('charges as charges_total', 'total')
            ->withSum('payments as payments_total', 'amount')
            ->get();

        return (float) $charges->sum(fn (Bill $bill) => max(
            0,
            (float) ($bill->charges_total ?? 0) - (float) ($bill->payments_total ?? 0),
        ));
    }

    /**
     * Revenue (payments received) bucketed over the range.
     *
     * @return array<string, mixed>
     */
    private function revenueTrend(Carbon $from, Carbon $to): array
    {
        $payments = Payment::whereBetween('created_at', [$from, $to])->get(['amount', 'created_at']);

        return $this->series($from, $to, $payments, 'created_at', fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    /**
     * Patient visits opened, bucketed over the range.
     *
     * @return array<string, mixed>
     */
    private function visitsTrend(Carbon $from, Carbon $to): array
    {
        $visits = Visit::whereBetween('opened_at', [$from, $to])->get(['id', 'opened_at']);

        return $this->series($from, $to, $visits, 'opened_at', fn (Collection $rows) => $rows->count());
    }

    /**
     * Revenue split by payment method.
     *
     * @return array<int, array<string, mixed>>
     */
    private function revenueByMethod(Carbon $from, Carbon $to): array
    {
        $byMethod = Payment::whereBetween('created_at', [$from, $to])
            ->get(['amount', 'method'])
            ->groupBy(fn (Payment $p) => $p->method->value);

        return collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $m) => [
                'key' => $m->value,
                'label' => $m->label(),
                'value' => round((float) ($byMethod->get($m->value)?->sum('amount') ?? 0), 2),
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * Appointments in the range grouped by their status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function appointmentsByStatus(Carbon $from, Carbon $to): array
    {
        $counts = Appointment::whereBetween('scheduled_start', [$from, $to])
            ->get(['id', 'status'])
            ->groupBy(fn (Appointment $a) => $a->status->value);

        return collect(AppointmentStatus::cases())
            ->map(fn (AppointmentStatus $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'value' => $counts->get($s->value)?->count() ?? 0,
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * Lab requisitions in the range grouped by their workflow status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function labByStatus(Carbon $from, Carbon $to): array
    {
        $counts = LabOrder::whereBetween('created_at', [$from, $to])
            ->get(['id', 'status'])
            ->groupBy(fn (LabOrder $o) => $o->status->value);

        return collect(LabOrderStatus::cases())
            ->map(fn (LabOrderStatus $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'value' => $counts->get($s->value)?->count() ?? 0,
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * The highest-volume dispensed items in the range, by quantity and revenue.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topDispensed(Carbon $from, Carbon $to): array
    {
        return DispenseItem::query()
            ->whereHas('dispense', fn ($q) => $q->where('status', Dispense::STATUS_DISPENSED)
                ->whereBetween('created_at', [$from, $to]))
            ->get(['name', 'quantity', 'total'])
            ->groupBy('name')
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'quantity' => (int) $rows->sum('quantity'),
                'revenue' => round((float) $rows->sum('total'), 2),
            ])
            ->sortByDesc('quantity')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Active stock items at or below their reorder level (a current snapshot).
     *
     * @return array<int, array<string, mixed>>
     */
    private function lowStock(): array
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->orderBy('quantity_on_hand')
            ->take(8)
            ->get(['id', 'name', 'code', 'quantity_on_hand', 'reorder_level', 'unit'])
            ->map(fn (InventoryItem $item) => [
                'name' => $item->name,
                'code' => $item->code,
                'on_hand' => $item->quantity_on_hand,
                'reorder_level' => $item->reorder_level,
                'unit' => $item->unit,
            ])
            ->all();
    }

    /**
     * Bucket a collection of records into a continuous, gap-filled time series.
     * Daily buckets for ranges up to 45 days, monthly buckets beyond that.
     *
     * @param  Collection<int, Model>  $records
     * @param  callable(Collection<int, Model>): (int|float)  $reduce
     * @return array<string, mixed>
     */
    private function series(Carbon $from, Carbon $to, Collection $records, string $dateColumn, callable $reduce): array
    {
        $monthly = $from->diffInDays($to) > 45;
        // Accept any Carbon variant — Eloquent casts dates to CarbonImmutable.
        $keyFor = fn (CarbonInterface $d) => $monthly ? $d->format('Y-m') : $d->format('Y-m-d');
        $labelFor = fn (CarbonInterface $d) => $monthly ? $d->isoFormat('MMM YYYY') : $d->isoFormat('D MMM');

        // Build the ordered, empty buckets first so gaps render as zero. The
        // cursor is a mutable Carbon so the increments below advance the loop.
        $buckets = [];
        $cursor = Carbon::instance($from)->startOfDay();
        while ($cursor <= $to) {
            $buckets[$keyFor($cursor)] = ['label' => $labelFor($cursor), 'value' => 0];
            $monthly ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }

        $grouped = $records->groupBy(fn ($record) => $keyFor($record->{$dateColumn}));
        foreach ($grouped as $key => $rows) {
            if (isset($buckets[$key])) {
                $buckets[$key]['value'] = $reduce($rows);
            }
        }

        return [
            'granularity' => $monthly ? 'month' : 'day',
            'points' => array_values($buckets),
        ];
    }
}
