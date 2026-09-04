<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\AppointmentStatus;
use App\Enums\BedStatus;
use App\Enums\BillStatus;
use App\Enums\ClaimStatus;
use App\Enums\LabOrderStatus;
use App\Enums\MaternalOutcome;
use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Birth;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\Dispense;
use App\Models\DispenseItem;
use App\Models\Encounter;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\StockBatch;
use App\Models\Ward;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The catalogue of available reports and the engine that runs them. Each report
 * declares its category and metadata here and resolves to a set of columns and
 * rows for a date range, so a single generic runner can render (and export) any
 * of them. Adding a report is a metadata entry plus a resolver arm.
 */
class ReportRegistry
{
    public function __construct(
        private readonly NhmisReports $nhmis,
        private readonly SurveillanceReports $surveillance,
    ) {}

    /**
     * Report categories, in display order.
     *
     * @return array<string, array{name: string, icon: string, description: string}>
     */
    public function categories(): array
    {
        return [
            'dashboards' => ['name' => 'Dashboards', 'icon' => 'LayoutGrid', 'description' => 'Interactive executive overviews.'],
            'finance' => ['name' => 'Finance', 'icon' => 'Banknote', 'description' => 'Revenue, payments and outstanding balances.'],
            'opd' => ['name' => 'Outpatient', 'icon' => 'Users', 'description' => 'Registrations, consultations and diagnoses.'],
            'laboratory' => ['name' => 'Laboratory', 'icon' => 'FlaskConical', 'description' => 'Requisitions and turnaround.'],
            'pharmacy' => ['name' => 'Pharmacy', 'icon' => 'Pill', 'description' => 'Dispensing activity and consumption.'],
            'inventory' => ['name' => 'Inventory', 'icon' => 'Package', 'description' => 'Stock levels, valuation and expiry.'],
            'appointments' => ['name' => 'Appointments', 'icon' => 'CalendarDays', 'description' => 'Scheduling, attendance and no-shows.'],
            'inpatient' => ['name' => 'Inpatient', 'icon' => 'BedDouble', 'description' => 'Admissions, discharges and bed occupancy.'],
            'insurance' => ['name' => 'Insurance', 'icon' => 'HandCoins', 'description' => 'NHIA and HMO claims and what payers still owe.'],
            'maternity' => ['name' => 'Maternity', 'icon' => 'Baby', 'description' => 'Delivery and birth registers.'],
            'nhmis' => ['name' => 'NHMIS returns', 'icon' => 'ClipboardList', 'description' => 'Monthly summary figures for the DHIS2 return. Pick "Last month" as the range.'],
            'surveillance' => ['name' => 'IDSR surveillance', 'icon' => 'Radar', 'description' => 'Notifiable disease cases for the DSNO: the line list and the weekly summary.'],
        ];
    }

    /**
     * The full report catalogue.
     *
     * @return array<int, array{key: string, name: string, category: string, description: string, icon: string, type: string, featured: bool, href?: string}>
     */
    public function reports(): array
    {
        return [
            ['key' => 'executive-overview', 'name' => 'Executive Overview', 'category' => 'dashboards', 'description' => 'KPIs, revenue trend and operational charts.', 'icon' => 'ChartColumn', 'type' => 'dashboard', 'featured' => true, 'href' => '/reports/overview'],

            ['key' => 'revenue-summary', 'name' => 'Revenue Summary', 'category' => 'finance', 'description' => 'Payments received per day over the period.', 'icon' => 'Banknote', 'type' => 'table', 'featured' => true],
            ['key' => 'payments-by-method', 'name' => 'Payments by Method', 'category' => 'finance', 'description' => 'Revenue split across payment methods.', 'icon' => 'CreditCard', 'type' => 'table', 'featured' => false],
            ['key' => 'outstanding-bills', 'name' => 'Outstanding Bills', 'category' => 'finance', 'description' => 'Bills still carrying an unpaid balance.', 'icon' => 'ReceiptText', 'type' => 'table', 'featured' => false],

            ['key' => 'patient-register', 'name' => 'Patient Register', 'category' => 'opd', 'description' => 'Patients registered within the period.', 'icon' => 'UserPlus', 'type' => 'table', 'featured' => true],
            ['key' => 'diagnoses-summary', 'name' => 'Diagnoses Summary', 'category' => 'opd', 'description' => 'Most frequent working diagnoses.', 'icon' => 'Stethoscope', 'type' => 'table', 'featured' => false],
            ['key' => 'consultations-log', 'name' => 'Consultations Log', 'category' => 'opd', 'description' => 'Completed consultations with outcomes.', 'icon' => 'ClipboardList', 'type' => 'table', 'featured' => false],

            ['key' => 'lab-orders', 'name' => 'Laboratory Orders', 'category' => 'laboratory', 'description' => 'Requisitions placed within the period.', 'icon' => 'FlaskConical', 'type' => 'table', 'featured' => false],
            ['key' => 'lab-turnaround', 'name' => 'Lab Turnaround', 'category' => 'laboratory', 'description' => 'Order-to-verification turnaround times.', 'icon' => 'Timer', 'type' => 'table', 'featured' => false],

            ['key' => 'dispensing-log', 'name' => 'Dispensing Log', 'category' => 'pharmacy', 'description' => 'Medications dispensed within the period.', 'icon' => 'Pill', 'type' => 'table', 'featured' => false],
            ['key' => 'top-dispensed', 'name' => 'Top Dispensed Items', 'category' => 'pharmacy', 'description' => 'Highest-volume items by quantity.', 'icon' => 'TrendingUp', 'type' => 'table', 'featured' => true],

            ['key' => 'low-stock', 'name' => 'Low Stock', 'category' => 'inventory', 'description' => 'Items at or below their reorder level.', 'icon' => 'PackageMinus', 'type' => 'table', 'featured' => true],
            ['key' => 'stock-valuation', 'name' => 'Stock Valuation', 'category' => 'inventory', 'description' => 'On-hand quantity and value by item.', 'icon' => 'Package', 'type' => 'table', 'featured' => false],
            ['key' => 'expiring-batches', 'name' => 'Expiring Batches', 'category' => 'inventory', 'description' => 'Batches expiring within 90 days.', 'icon' => 'CalendarClock', 'type' => 'table', 'featured' => false],

            ['key' => 'appointments-log', 'name' => 'Appointments Log', 'category' => 'appointments', 'description' => 'Appointments scheduled within the period.', 'icon' => 'CalendarDays', 'type' => 'table', 'featured' => false],
            ['key' => 'no-shows', 'name' => 'No-shows', 'category' => 'appointments', 'description' => 'Appointments recorded as no-shows.', 'icon' => 'CalendarX', 'type' => 'table', 'featured' => false],

            ['key' => 'admissions-log', 'name' => 'Admissions Log', 'category' => 'inpatient', 'description' => 'Patients admitted within the period, with outcome and length of stay.', 'icon' => 'BedDouble', 'type' => 'table', 'featured' => true],
            ['key' => 'bed-occupancy', 'name' => 'Bed Occupancy', 'category' => 'inpatient', 'description' => 'Beds occupied, available and out of service per ward, right now.', 'icon' => 'Bed', 'type' => 'table', 'featured' => false],

            ['key' => 'claims-register', 'name' => 'Claims Register', 'category' => 'insurance', 'description' => 'Claims raised within the period, with what was claimed and paid.', 'icon' => 'HandCoins', 'type' => 'table', 'featured' => true],
            ['key' => 'claims-outstanding', 'name' => 'Outstanding by Payer', 'category' => 'insurance', 'description' => 'Submitted claims still awaiting remittance, grouped by payer.', 'icon' => 'Landmark', 'type' => 'table', 'featured' => false],

            ['key' => 'delivery-register', 'name' => 'Delivery Register', 'category' => 'maternity', 'description' => 'Deliveries within the period: mode, attendant, complications and outcome.', 'icon' => 'Baby', 'type' => 'table', 'featured' => true],
            ['key' => 'birth-register', 'name' => 'Birth Register', 'category' => 'maternity', 'description' => 'Every baby born within the period, with weight, Apgar and outcome.', 'icon' => 'Baby', 'type' => 'table', 'featured' => false],

            ['key' => 'nhmis-opd-attendance', 'name' => 'NHMIS: OPD Attendance', 'category' => 'nhmis', 'description' => 'New and repeat out-patient attendance by sex and age group.', 'icon' => 'Users', 'type' => 'table', 'featured' => false],
            ['key' => 'nhmis-morbidity', 'name' => 'NHMIS: Morbidity', 'category' => 'nhmis', 'description' => 'Coded diagnoses grouped into the NHMIS disease lines by age band and sex.', 'icon' => 'Stethoscope', 'type' => 'table', 'featured' => true],
            ['key' => 'nhmis-inpatient', 'name' => 'NHMIS: In-patient', 'category' => 'nhmis', 'description' => 'Admissions, discharges, deaths, patient-days and bed occupancy.', 'icon' => 'BedDouble', 'type' => 'table', 'featured' => false],
            ['key' => 'nhmis-mch', 'name' => 'NHMIS: Maternal & Child Health', 'category' => 'nhmis', 'description' => 'Antenatal care, family planning and immunisation doses by antigen.', 'icon' => 'Baby', 'type' => 'table', 'featured' => false],
            ['key' => 'nhmis-laboratory', 'name' => 'NHMIS: Laboratory', 'category' => 'nhmis', 'description' => 'Tests performed and abnormal results, with malaria testing figures.', 'icon' => 'FlaskConical', 'type' => 'table', 'featured' => false],

            ['key' => 'idsr-line-list', 'name' => 'IDSR: Case Line List', 'category' => 'surveillance', 'description' => 'One row per notifiable disease case detected in the period, with patient contact details, classification, outcome and DSNO notification.', 'icon' => 'Radar', 'type' => 'table', 'featured' => true],
            ['key' => 'idsr-weekly-summary', 'name' => 'IDSR: Weekly Summary', 'category' => 'surveillance', 'description' => 'Cases, confirmed cases and deaths per priority disease for the IDSR 002 return. Pick a single week as the range.', 'icon' => 'ClipboardList', 'type' => 'table', 'featured' => false],
        ];
    }

    /**
     * Look up a single report definition by key.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return collect($this->reports())->firstWhere('key', $key);
    }

    /**
     * Run a report for a date range.
     *
     * @return array{columns: array<int, array{key: string, label: string, align: string}>, rows: array<int, array<string, string>>, summary: array<int, array{label: string, value: string}>}
     */
    public function run(string $key, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return match ($key) {
            'revenue-summary' => $this->revenueSummary($from, $to),
            'payments-by-method' => $this->paymentsByMethod($from, $to),
            'outstanding-bills' => $this->outstandingBills(),
            'patient-register' => $this->patientRegister($from, $to),
            'diagnoses-summary' => $this->diagnosesSummary($from, $to),
            'consultations-log' => $this->consultationsLog($from, $to),
            'lab-orders' => $this->labOrders($from, $to),
            'lab-turnaround' => $this->labTurnaround($from, $to),
            'dispensing-log' => $this->dispensingLog($from, $to),
            'top-dispensed' => $this->topDispensed($from, $to),
            'low-stock' => $this->lowStock(),
            'stock-valuation' => $this->stockValuation(),
            'expiring-batches' => $this->expiringBatches(),
            'appointments-log' => $this->appointmentsLog($from, $to),
            'no-shows' => $this->noShows($from, $to),
            'admissions-log' => $this->admissionsLog($from, $to),
            'bed-occupancy' => $this->bedOccupancy(),
            'claims-register' => $this->claimsRegister($from, $to),
            'claims-outstanding' => $this->claimsOutstanding(),
            'delivery-register' => $this->deliveryRegister($from, $to),
            'birth-register' => $this->birthRegister($from, $to),
            'nhmis-opd-attendance' => $this->nhmis->opdAttendance($from, $to),
            'nhmis-morbidity' => $this->nhmis->morbidity($from, $to),
            'nhmis-inpatient' => $this->nhmis->inpatient($from, $to),
            'nhmis-mch' => $this->nhmis->maternalChildHealth($from, $to),
            'nhmis-laboratory' => $this->nhmis->laboratory($from, $to),
            'idsr-line-list' => $this->surveillance->lineList($from, $to),
            'idsr-weekly-summary' => $this->surveillance->weeklySummary($from, $to),
            default => ['columns' => [], 'rows' => [], 'summary' => []],
        };
    }

    // ------------------------------------------------------------------ Finance

    /**
     * @return array<string, mixed>
     */
    private function revenueSummary(Carbon $from, Carbon $to): array
    {
        $payments = Payment::whereBetween('created_at', [$from, $to])->get(['amount', 'created_at']);

        $rows = $payments
            ->groupBy(fn (Payment $p) => $p->created_at->format('Y-m-d'))
            ->map(fn (Collection $g, string $day) => [
                'date' => Carbon::parse($day)->isoFormat('ddd, D MMM YYYY'),
                'count' => (string) $g->count(),
                'amount' => $this->money($g->sum('amount')),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Date', 'align' => 'left'],
                ['key' => 'count', 'label' => 'Payments', 'align' => 'right'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total revenue', 'value' => $this->money($payments->sum('amount'))],
                ['label' => 'Payments', 'value' => (string) $payments->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentsByMethod(Carbon $from, Carbon $to): array
    {
        $payments = Payment::whereBetween('created_at', [$from, $to])->get(['amount', 'method']);

        $rows = $payments
            ->groupBy(fn (Payment $p) => $p->method->label())
            ->map(fn (Collection $g, string $method) => [
                'amount_raw' => (float) $g->sum('amount'),
                'method' => $method,
                'count' => (string) $g->count(),
                'amount' => $this->money($g->sum('amount')),
            ])
            ->sortByDesc('amount_raw')
            ->map(fn (array $r) => collect($r)->except('amount_raw')->all())
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'method', 'label' => 'Method', 'align' => 'left'],
                ['key' => 'count', 'label' => 'Payments', 'align' => 'right'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total revenue', 'value' => $this->money($payments->sum('amount'))],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outstandingBills(): array
    {
        $bills = Bill::query()
            ->whereIn('status', [BillStatus::Open->value, BillStatus::PartiallyPaid->value])
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->withSum('charges as charges_total', 'total')
            ->withSum('payments as payments_total', 'amount')
            ->get();

        $rows = $bills
            ->map(fn (Bill $b) => [
                'balance_raw' => (float) ($b->charges_total ?? 0) - (float) ($b->payments_total ?? 0),
                'file_number' => $b->patient?->file_number ?? '—',
                'patient' => $b->patient?->fullName() ?? '—',
                'status' => $b->status->label(),
                'total' => $this->money((float) ($b->charges_total ?? 0)),
                'paid' => $this->money((float) ($b->payments_total ?? 0)),
                'balance' => $this->money((float) ($b->charges_total ?? 0) - (float) ($b->payments_total ?? 0)),
            ])
            ->filter(fn (array $r) => $r['balance_raw'] > 0)
            ->sortByDesc('balance_raw')
            ->map(fn (array $r) => collect($r)->except('balance_raw')->all())
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'file_number', 'label' => 'File no.', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'left'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'right'],
                ['key' => 'balance', 'label' => 'Balance', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Outstanding bills', 'value' => (string) count($rows)],
                ['label' => 'Total owed', 'value' => $this->money(collect($rows)->sum(fn ($r) => (float) str_replace(['₦', ','], '', $r['balance'])))],
            ],
        ];
    }

    // -------------------------------------------------------------- Outpatient

    /**
     * @return array<string, mixed>
     */
    private function patientRegister(Carbon $from, Carbon $to): array
    {
        $patients = Patient::whereBetween('created_at', [$from, $to])->latest()->get();

        $rows = $patients->map(fn (Patient $p) => [
            'file_number' => $p->file_number,
            'name' => $p->fullName(),
            'sex' => $p->sex,
            'age' => $p->age() !== null ? $p->age().'y' : '—',
            'phone' => $p->phone ?? '—',
            'registered' => $p->created_at?->isoFormat('D MMM YYYY') ?? '—',
        ])->all();

        return [
            'columns' => [
                ['key' => 'file_number', 'label' => 'File no.', 'align' => 'left'],
                ['key' => 'name', 'label' => 'Name', 'align' => 'left'],
                ['key' => 'sex', 'label' => 'Sex', 'align' => 'left'],
                ['key' => 'age', 'label' => 'Age', 'align' => 'right'],
                ['key' => 'phone', 'label' => 'Phone', 'align' => 'left'],
                ['key' => 'registered', 'label' => 'Registered', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'New patients', 'value' => (string) $patients->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosesSummary(Carbon $from, Carbon $to): array
    {
        $diagnoses = Encounter::consultations()->signed()
            ->whereBetween('signed_at', [$from, $to])
            ->whereNotNull('assessment')
            ->where('assessment', '!=', '')
            ->pluck('assessment')
            ->map(fn (string $d) => trim($d));

        $rows = $diagnoses
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $name) => ['diagnosis' => $name, 'count' => (string) $count])
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'diagnosis', 'label' => 'Diagnosis', 'align' => 'left'],
                ['key' => 'count', 'label' => 'Cases', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Distinct diagnoses', 'value' => (string) count($rows)],
                ['label' => 'Total cases', 'value' => (string) $diagnoses->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultationsLog(Carbon $from, Carbon $to): array
    {
        $encounters = Encounter::consultations()->signed()
            ->whereBetween('signed_at', [$from, $to])
            ->with(['patient:id,file_number,surname,first_name,other_names', 'author:id,name'])
            ->latest('signed_at')
            ->get();

        $rows = $encounters->map(fn (Encounter $e) => [
            'date' => $e->signed_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—',
            'patient' => $e->patient?->fullName() ?? '—',
            'clinician' => $e->author?->name ?? '—',
            'diagnosis' => $e->diagnosisSummary() ?? '—',
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Completed', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'clinician', 'label' => 'Clinician', 'align' => 'left'],
                ['key' => 'diagnosis', 'label' => 'Diagnosis', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Consultations', 'value' => (string) $encounters->count()],
            ],
        ];
    }

    // -------------------------------------------------------------- Laboratory

    /**
     * @return array<string, mixed>
     */
    private function labOrders(Carbon $from, Carbon $to): array
    {
        $orders = LabOrder::whereBetween('created_at', [$from, $to])
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->latest()
            ->get();

        $rows = $orders->map(fn (LabOrder $o) => [
            'accession' => $o->accession_number,
            'patient' => $o->patient?->fullName() ?? '—',
            'status' => $o->status->label(),
            'priority' => $o->priority->label(),
            'ordered' => $o->created_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—',
        ])->all();

        return [
            'columns' => [
                ['key' => 'accession', 'label' => 'Accession', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'left'],
                ['key' => 'priority', 'label' => 'Priority', 'align' => 'left'],
                ['key' => 'ordered', 'label' => 'Ordered', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Orders', 'value' => (string) $orders->count()],
                ['label' => 'Completed', 'value' => (string) $orders->where('status', LabOrderStatus::Completed)->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labTurnaround(Carbon $from, Carbon $to): array
    {
        $orders = LabOrder::whereBetween('created_at', [$from, $to])
            ->whereNotNull('verified_at')
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->get();

        $withHours = $orders->map(fn (LabOrder $o) => [
            'order' => $o,
            'hours' => $o->created_at->diffInMinutes($o->verified_at) / 60,
        ]);

        $rows = $withHours
            ->sortByDesc('hours')
            ->map(fn (array $r) => [
                'accession' => $r['order']->accession_number,
                'patient' => $r['order']->patient?->fullName() ?? '—',
                'ordered' => $r['order']->created_at->isoFormat('D MMM, HH:mm'),
                'verified' => $r['order']->verified_at->isoFormat('D MMM, HH:mm'),
                'turnaround' => number_format($r['hours'], 1).' h',
            ])
            ->values()
            ->all();

        $avg = $withHours->avg('hours');

        return [
            'columns' => [
                ['key' => 'accession', 'label' => 'Accession', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'ordered', 'label' => 'Ordered', 'align' => 'left'],
                ['key' => 'verified', 'label' => 'Verified', 'align' => 'left'],
                ['key' => 'turnaround', 'label' => 'Turnaround', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Verified orders', 'value' => (string) $orders->count()],
                ['label' => 'Avg turnaround', 'value' => $avg ? number_format($avg, 1).' h' : '—'],
            ],
        ];
    }

    // ---------------------------------------------------------------- Pharmacy

    /**
     * @return array<string, mixed>
     */
    private function dispensingLog(Carbon $from, Carbon $to): array
    {
        $dispenses = Dispense::where('status', Dispense::STATUS_DISPENSED)
            ->whereBetween('created_at', [$from, $to])
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->withCount('items')
            ->withSum('items as items_total', 'total')
            ->latest()
            ->get();

        $rows = $dispenses->map(fn (Dispense $d) => [
            'date' => $d->created_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—',
            'patient' => $d->patient?->fullName() ?? '—',
            'items' => (string) $d->items_count,
            'total' => $this->money((float) ($d->items_total ?? 0)),
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Dispensed', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'items', 'label' => 'Items', 'align' => 'right'],
                ['key' => 'total', 'label' => 'Value', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Dispenses', 'value' => (string) $dispenses->count()],
                ['label' => 'Total value', 'value' => $this->money((float) $dispenses->sum('items_total'))],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topDispensed(Carbon $from, Carbon $to): array
    {
        $rows = DispenseItem::query()
            ->whereHas('dispense', fn ($q) => $q->where('status', Dispense::STATUS_DISPENSED)
                ->whereBetween('created_at', [$from, $to]))
            ->get(['name', 'quantity', 'total'])
            ->groupBy('name')
            ->map(fn (Collection $g, string $name) => [
                'quantity_raw' => (int) $g->sum('quantity'),
                'name' => $name,
                'quantity' => (string) $g->sum('quantity'),
                'revenue' => $this->money((float) $g->sum('total')),
            ])
            ->sortByDesc('quantity_raw')
            ->map(fn (array $r) => collect($r)->except('quantity_raw')->all())
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Item', 'align' => 'left'],
                ['key' => 'quantity', 'label' => 'Quantity', 'align' => 'right'],
                ['key' => 'revenue', 'label' => 'Revenue', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Distinct items', 'value' => (string) count($rows)],
            ],
        ];
    }

    // --------------------------------------------------------------- Inventory

    /**
     * @return array<string, mixed>
     */
    private function lowStock(): array
    {
        $items = InventoryItem::where('is_active', true)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->orderBy('quantity_on_hand')
            ->get();

        $rows = $items->map(fn (InventoryItem $i) => [
            'name' => $i->name,
            'code' => $i->code,
            'on_hand' => $i->quantity_on_hand.($i->unit ? ' '.$i->unit : ''),
            'reorder_level' => (string) $i->reorder_level,
            'category' => $i->category->label(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Item', 'align' => 'left'],
                ['key' => 'code', 'label' => 'Code', 'align' => 'left'],
                ['key' => 'category', 'label' => 'Category', 'align' => 'left'],
                ['key' => 'on_hand', 'label' => 'On hand', 'align' => 'right'],
                ['key' => 'reorder_level', 'label' => 'Reorder at', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Items below reorder', 'value' => (string) $items->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockValuation(): array
    {
        $items = InventoryItem::where('is_active', true)->orderBy('name')->get();

        $rows = $items->map(fn (InventoryItem $i) => [
            'name' => $i->name,
            'category' => $i->category->label(),
            'on_hand' => $i->quantity_on_hand.($i->unit ? ' '.$i->unit : ''),
            'unit_cost' => $i->cost_price !== null ? $this->money((float) $i->cost_price) : '—',
            'value' => $this->money((float) $i->quantity_on_hand * (float) ($i->cost_price ?? 0)),
        ])->all();

        $totalValue = $items->sum(fn (InventoryItem $i) => (float) $i->quantity_on_hand * (float) ($i->cost_price ?? 0));

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Item', 'align' => 'left'],
                ['key' => 'category', 'label' => 'Category', 'align' => 'left'],
                ['key' => 'on_hand', 'label' => 'On hand', 'align' => 'right'],
                ['key' => 'unit_cost', 'label' => 'Unit cost', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Line items', 'value' => (string) $items->count()],
                ['label' => 'Total stock value', 'value' => $this->money($totalValue)],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expiringBatches(): array
    {
        $cutoff = Carbon::today()->addDays(90);

        $batches = StockBatch::query()
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $cutoff)
            ->with('item:id,name,code')
            ->orderBy('expiry_date')
            ->get();

        $rows = $batches->map(fn (StockBatch $b) => [
            'item' => $b->item?->name ?? '—',
            'batch' => $b->batch_number ?? '—',
            'quantity' => (string) $b->quantity,
            'expiry' => $b->expiry_date->isoFormat('D MMM YYYY'),
            'days_left' => (string) Carbon::today()->diffInDays($b->expiry_date, false),
        ])->all();

        return [
            'columns' => [
                ['key' => 'item', 'label' => 'Item', 'align' => 'left'],
                ['key' => 'batch', 'label' => 'Batch', 'align' => 'left'],
                ['key' => 'quantity', 'label' => 'Quantity', 'align' => 'right'],
                ['key' => 'expiry', 'label' => 'Expiry', 'align' => 'left'],
                ['key' => 'days_left', 'label' => 'Days left', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Batches expiring ≤ 90 days', 'value' => (string) $batches->count()],
            ],
        ];
    }

    // ------------------------------------------------------------ Appointments

    /**
     * @return array<string, mixed>
     */
    private function appointmentsLog(Carbon $from, Carbon $to): array
    {
        $appointments = Appointment::whereBetween('scheduled_start', [$from, $to])
            ->with(['patient:id,file_number,surname,first_name,other_names', 'provider:id,name'])
            ->orderBy('scheduled_start')
            ->get();

        $rows = $appointments->map(fn (Appointment $a) => [
            'when' => $a->scheduled_start->isoFormat('D MMM YYYY, HH:mm'),
            'patient' => $a->patient?->fullName() ?? '—',
            'provider' => $a->provider?->name ?? 'Unassigned',
            'status' => $a->status->label(),
            'source' => $a->source->label(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'when', 'label' => 'Scheduled', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'provider', 'label' => 'Provider', 'align' => 'left'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'left'],
                ['key' => 'source', 'label' => 'Source', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Appointments', 'value' => (string) $appointments->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noShows(Carbon $from, Carbon $to): array
    {
        $appointments = Appointment::where('status', AppointmentStatus::NoShow->value)
            ->whereBetween('scheduled_start', [$from, $to])
            ->with(['patient:id,file_number,surname,first_name,other_names', 'provider:id,name'])
            ->orderByDesc('scheduled_start')
            ->get();

        $rows = $appointments->map(fn (Appointment $a) => [
            'when' => $a->scheduled_start->isoFormat('D MMM YYYY, HH:mm'),
            'patient' => $a->patient?->fullName() ?? '—',
            'provider' => $a->provider?->name ?? 'Unassigned',
        ])->all();

        return [
            'columns' => [
                ['key' => 'when', 'label' => 'Scheduled', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'provider', 'label' => 'Provider', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'No-shows', 'value' => (string) $appointments->count()],
            ],
        ];
    }

    /**
     * Format a value as Naira currency.
     */
    // --------------------------------------------------------------- Inpatient

    /**
     * @return array<string, mixed>
     */
    private function admissionsLog(Carbon $from, Carbon $to): array
    {
        $admissions = Admission::query()
            ->whereNotNull('admitted_at')
            ->whereBetween('admitted_at', [$from, $to])
            ->with(['patient:id,file_number,surname,first_name,other_names', 'ward:id,name', 'attending:id,name'])
            ->latest('admitted_at')
            ->get();

        $rows = $admissions->map(fn (Admission $a) => [
            'number' => $a->admission_number,
            'patient' => $a->patient?->fullName() ?? '—',
            'ward' => $a->ward?->name ?? '—',
            'attending' => $a->attending?->name ?? '—',
            'admitted' => $a->admitted_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—',
            'discharged' => $a->discharged_at?->isoFormat('D MMM YYYY, HH:mm') ?? '—',
            'days' => (string) ($a->lengthOfStayDays() ?? '—'),
            'outcome' => $a->discharge_type?->label() ?? $a->status->label(),
        ])->all();

        $completed = $admissions->whereNotNull('discharged_at');

        return [
            'columns' => [
                ['key' => 'number', 'label' => 'Admission', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'ward', 'label' => 'Ward', 'align' => 'left'],
                ['key' => 'attending', 'label' => 'Attending', 'align' => 'left'],
                ['key' => 'admitted', 'label' => 'Admitted', 'align' => 'left'],
                ['key' => 'discharged', 'label' => 'Discharged', 'align' => 'left'],
                ['key' => 'days', 'label' => 'Days', 'align' => 'right'],
                ['key' => 'outcome', 'label' => 'Outcome', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Admissions', 'value' => (string) $admissions->count()],
                ['label' => 'Still admitted', 'value' => (string) $admissions->where('status', AdmissionStatus::Admitted)->count()],
                ['label' => 'Avg. stay (days)', 'value' => $completed->isEmpty()
                    ? '—'
                    : number_format($completed->avg(fn (Admission $a) => $a->lengthOfStayDays() ?? 0), 1)],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bedOccupancy(): array
    {
        $wards = Ward::query()->with('beds')->orderBy('sort_order')->orderBy('name')->get();

        $rows = $wards->map(function (Ward $ward) {
            $o = $ward->occupancy();
            $usable = $o['total'] - $o['out_of_service'];

            return [
                'ward' => $ward->name.($ward->is_active ? '' : ' (inactive)'),
                'beds' => (string) $o['total'],
                'occupied' => (string) $o['occupied'],
                'available' => (string) $o['available'],
                'out_of_service' => (string) $o['out_of_service'],
                'rate' => $usable > 0 ? number_format($o['occupied'] / $usable * 100, 0).'%' : '—',
            ];
        })->all();

        $totals = [
            'beds' => $wards->sum(fn (Ward $w) => $w->beds->count()),
            'occupied' => $wards->sum(fn (Ward $w) => $w->beds->where('status', BedStatus::Occupied)->count()),
            'out' => $wards->sum(fn (Ward $w) => $w->beds->where('status', BedStatus::OutOfService)->count()),
        ];
        $usable = $totals['beds'] - $totals['out'];

        return [
            'columns' => [
                ['key' => 'ward', 'label' => 'Ward', 'align' => 'left'],
                ['key' => 'beds', 'label' => 'Beds', 'align' => 'right'],
                ['key' => 'occupied', 'label' => 'Occupied', 'align' => 'right'],
                ['key' => 'available', 'label' => 'Available', 'align' => 'right'],
                ['key' => 'out_of_service', 'label' => 'Out of service', 'align' => 'right'],
                ['key' => 'rate', 'label' => 'Occupancy', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Beds', 'value' => (string) $totals['beds']],
                ['label' => 'Occupied', 'value' => (string) $totals['occupied']],
                ['label' => 'Occupancy', 'value' => $usable > 0 ? number_format($totals['occupied'] / $usable * 100, 0).'%' : '—'],
            ],
        ];
    }

    // --------------------------------------------------------------- Maternity

    /**
     * @return array<string, mixed>
     */
    private function deliveryRegister(Carbon $from, Carbon $to): array
    {
        $deliveries = Delivery::query()
            ->whereBetween('delivered_at', [$from, $to])
            ->with(['patient:id,file_number,surname,first_name,other_names,date_of_birth', 'attendant:id,name', 'births'])
            ->latest('delivered_at')
            ->get();

        $rows = $deliveries->map(fn (Delivery $d) => [
            'date' => $d->delivered_at->isoFormat('D MMM YYYY, HH:mm'),
            'mother' => $d->patient?->fullName() ?? '—',
            'file_number' => $d->patient?->file_number ?? '—',
            'age' => (string) ($d->patient?->age() ?? '—'),
            'ga' => $d->gestational_age_weeks ? $d->gestational_age_weeks.' wks' : '—',
            'mode' => $d->mode->label(),
            'attendant' => $d->attendant?->name ?? '—',
            'babies' => $d->births->map(fn (Birth $b) => $b->sex.' '.($b->outcome->isLive() ? 'live' : 'SB').($b->weight_grams ? ' '.number_format($b->weight_grams / 1000, 2).'kg' : ''))->implode('; '),
            'complications' => implode(', ', $d->complications ?? []) ?: '—',
            'outcome' => $d->maternal_outcome->label(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Delivered', 'align' => 'left'],
                ['key' => 'mother', 'label' => 'Mother', 'align' => 'left'],
                ['key' => 'file_number', 'label' => 'File no.', 'align' => 'left'],
                ['key' => 'age', 'label' => 'Age', 'align' => 'right'],
                ['key' => 'ga', 'label' => 'Gestation', 'align' => 'right'],
                ['key' => 'mode', 'label' => 'Mode', 'align' => 'left'],
                ['key' => 'attendant', 'label' => 'Attendant', 'align' => 'left'],
                ['key' => 'babies', 'label' => 'Babies', 'align' => 'left'],
                ['key' => 'complications', 'label' => 'Complications', 'align' => 'left'],
                ['key' => 'outcome', 'label' => 'Mother', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Deliveries', 'value' => (string) $deliveries->count()],
                ['label' => 'Caesarean sections', 'value' => (string) $deliveries->filter(fn (Delivery $d) => $d->mode->isCaesarean())->count()],
                ['label' => 'Maternal deaths', 'value' => (string) $deliveries->where('maternal_outcome', MaternalOutcome::Deceased)->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function birthRegister(Carbon $from, Carbon $to): array
    {
        $births = Birth::query()
            ->whereHas('delivery', fn ($q) => $q->whereBetween('delivered_at', [$from, $to]))
            ->with(['delivery:id,delivered_at,mode', 'mother:id,file_number,surname,first_name,other_names', 'newborn:id,file_number'])
            ->get()
            ->sortByDesc(fn (Birth $b) => $b->delivery->delivered_at)
            ->values();

        $rows = $births->map(fn (Birth $b) => [
            'date' => $b->delivery->delivered_at->isoFormat('D MMM YYYY, HH:mm'),
            'mother' => $b->mother?->fullName() ?? '—',
            'baby' => $b->newborn?->file_number ?? ('Baby '.$b->birth_order),
            'sex' => $b->sex,
            'outcome' => $b->outcome->label(),
            'weight' => $b->weight_grams ? number_format($b->weight_grams / 1000, 2).' kg'.($b->isLowBirthWeight() ? ' (LBW)' : '') : '—',
            'apgar' => $b->apgar_1 !== null || $b->apgar_5 !== null ? ($b->apgar_1 ?? '—').' / '.($b->apgar_5 ?? '—') : '—',
            'mode' => $b->delivery->mode->label(),
            'condition' => Birth::CONDITIONS[$b->condition] ?? ($b->condition ?? '—'),
        ])->all();

        $live = $births->filter(fn (Birth $b) => $b->outcome->isLive());

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Born', 'align' => 'left'],
                ['key' => 'mother', 'label' => 'Mother', 'align' => 'left'],
                ['key' => 'baby', 'label' => 'Baby', 'align' => 'left'],
                ['key' => 'sex', 'label' => 'Sex', 'align' => 'left'],
                ['key' => 'outcome', 'label' => 'Outcome', 'align' => 'left'],
                ['key' => 'weight', 'label' => 'Weight', 'align' => 'right'],
                ['key' => 'apgar', 'label' => 'Apgar 1 / 5', 'align' => 'right'],
                ['key' => 'mode', 'label' => 'Delivery', 'align' => 'left'],
                ['key' => 'condition', 'label' => 'Condition', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Births', 'value' => (string) $births->count()],
                ['label' => 'Live births', 'value' => (string) $live->count()],
                ['label' => 'Stillbirths', 'value' => (string) ($births->count() - $live->count())],
                ['label' => 'Low birth weight', 'value' => (string) $live->filter(fn (Birth $b) => $b->isLowBirthWeight())->count()],
            ],
        ];
    }

    // --------------------------------------------------------------- Insurance

    /**
     * @return array<string, mixed>
     */
    private function claimsRegister(Carbon $from, Carbon $to): array
    {
        $claims = Claim::query()
            ->whereBetween('created_at', [$from, $to])
            ->with(['patient:id,file_number,surname,first_name,other_names', 'payer:id,name', 'batch:id,batch_number'])
            ->latest('created_at')
            ->get();

        $rows = $claims->map(fn (Claim $c) => [
            'number' => $c->claim_number,
            'patient' => $c->patient?->fullName() ?? '—',
            'payer' => $c->payer?->name ?? '—',
            'service_date' => $c->service_date->isoFormat('D MMM YYYY'),
            'batch' => $c->batch?->batch_number ?? '—',
            'claimed' => $this->money($c->payer_amount),
            'paid' => $this->money($c->paid_amount),
            'status' => $c->status->label(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'number', 'label' => 'Claim', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'payer', 'label' => 'Payer', 'align' => 'left'],
                ['key' => 'service_date', 'label' => 'Service date', 'align' => 'left'],
                ['key' => 'batch', 'label' => 'Schedule', 'align' => 'left'],
                ['key' => 'claimed', 'label' => 'Claimed', 'align' => 'right'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'right'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Claims', 'value' => (string) $claims->count()],
                ['label' => 'Claimed', 'value' => $this->money($claims->sum('payer_amount'))],
                ['label' => 'Paid', 'value' => $this->money($claims->sum('paid_amount'))],
                ['label' => 'Rejected', 'value' => (string) $claims->where('status', ClaimStatus::Rejected)->count()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function claimsOutstanding(): array
    {
        $claims = Claim::query()->outstanding()->with('payer:id,name')->get();

        $rows = $claims
            ->groupBy('payer_id')
            ->map(function (Collection $group) {
                $oldest = $group->min('submitted_at');

                return [
                    'payer' => $group->first()->payer?->name ?? '—',
                    'count' => (string) $group->count(),
                    'outstanding' => $this->money($group->sum(fn (Claim $c) => $c->outstandingAmount())),
                    'oldest' => $oldest ? Carbon::parse($oldest)->diffInDays(now()).' days' : '—',
                ];
            })
            ->sortBy('payer')
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'payer', 'label' => 'Payer', 'align' => 'left'],
                ['key' => 'count', 'label' => 'Claims', 'align' => 'right'],
                ['key' => 'outstanding', 'label' => 'Outstanding', 'align' => 'right'],
                ['key' => 'oldest', 'label' => 'Oldest submission', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Claims', 'value' => (string) $claims->count()],
                ['label' => 'Outstanding', 'value' => $this->money($claims->sum(fn (Claim $c) => $c->outstandingAmount()))],
            ],
        ];
    }

    private function money(float|int $value): string
    {
        return '₦'.number_format((float) $value, 2);
    }
}
