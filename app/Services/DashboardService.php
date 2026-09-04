<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\AppointmentStatus;
use App\Enums\BedStatus;
use App\Enums\BillStatus;
use App\Enums\ClaimStatus;
use App\Enums\LabOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Claim;
use App\Models\Dispense;
use App\Models\Encounter;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\StockBatch;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Assembles the home screen: a "today" strip of tiles, work-surface panels
 * for the modules the signed-in user can reach, and alerts that need action.
 *
 * Everything is about right now or today. Trends over a period live in the
 * executive overview under Reports, which management is linked to from here.
 */
class DashboardService
{
    private const LIST_LIMIT = 8;

    private const TILE_LIMIT = 8;

    private const LONG_WAIT_MINUTES = 60;

    private const EXPIRY_WINDOW_DAYS = 90;

    private const STALE_CLAIM_DAYS = 7;

    private const OVERDUE_BILL_DAYS = 30;

    /**
     * Tile order for management roles, who want the facility picture before
     * any one department's worklist.
     *
     * @var list<string>
     */
    private const MANAGEMENT_TILES = [
        'waiting', 'registrations_today', 'appointments_today', 'collected_today',
        'outstanding', 'admitted_now', 'bed_occupancy', 'low_stock',
    ];

    public function __construct(private readonly ReportService $reports) {}

    /**
     * Build the home payload for a user.
     *
     * @param  array<int, string>  $modules  Slugs of the modules the user can reach.
     * @return array<string, mixed>
     */
    public function home(User $user, array $modules): array
    {
        $has = fn (string ...$slugs): bool => array_intersect($slugs, $modules) !== [];

        return [
            'tiles' => $this->tiles($user, $modules),
            'sections' => [
                'queues' => $has('queues') ? $this->queues() : null,
                'clinical' => $has('clinical') ? $this->clinical($user) : null,
                'nursing' => $has('nursing') ? $this->nursing() : null,
                'appointments' => $has('appointments') && ! $has('clinical') ? $this->appointments() : null,
                'laboratory' => $has('laboratory') ? $this->laboratory() : null,
                'pharmacy' => $has('pharmacy') ? $this->pharmacy() : null,
                'admissions' => $has('admissions') ? $this->admissions() : null,
                'billing' => $has('billing') ? $this->billing($user) : null,
                'claims' => $has('claims') ? $this->claims() : null,
                'management' => $has('reports') ? $this->management() : null,
            ],
            'alerts' => $this->alerts($modules),
        ];
    }

    // ------------------------------------------------------------------ Tiles

    /**
     * The "today" strip: one tile per figure the user acts on, chosen by the
     * modules they can reach and capped so the strip stays scannable.
     *
     * @param  array<int, string>  $modules
     * @return list<array<string, mixed>>
     */
    private function tiles(User $user, array $modules): array
    {
        $has = fn (string ...$slugs): bool => array_intersect($slugs, $modules) !== [];
        $tiles = collect();

        $add = function (string $key, string $label, int|float $value, string $icon, string $href, ?string $sub = null, string $format = 'number') use ($tiles): void {
            if (! $tiles->has($key)) {
                $tiles->put($key, compact('key', 'label', 'value', 'format', 'icon', 'href', 'sub'));
            }
        };

        if ($has('clinical')) {
            $add('my_waiting', 'Waiting for me', $this->waitingAt('clinical', $user)->count(), 'Stethoscope', '/clinical', 'Consultation queue');
            $add('seen_today', 'Seen today', $this->signedTodayBy($user), 'ClipboardCheck', '/clinical', 'Consultations signed');
            $add('my_appointments_today', 'My appointments', $this->appointmentsToday($user)->count(), 'CalendarDays', '/appointments', 'Booked for today');
        }

        if ($has('nursing')) {
            $add('nursing_waiting', 'Waiting at nursing', $this->waitingAt('nursing')->count(), 'HeartPulse', '/nursing', 'Triage, ANC, immunization');
            $add('admitted_now', 'On the wards', $this->admittedNow(), 'BedDouble', '/admissions', 'Inpatients right now');
        }

        if ($has('registration')) {
            $add('registrations_today', 'Registered today', Patient::query()->whereBetween('created_at', $this->today())->count(), 'UserPlus', '/patients', 'New patient folders');
        }

        if ($has('queues')) {
            $add('waiting', 'Patients waiting', QueueEntry::query()->where('status', QueueStatus::Waiting->value)->count(), 'ListChecks', '/queues', 'Across all service points');
            $add('avg_wait_today', 'Average wait', $this->averageWaitMinutesToday(), 'Clock', '/queues', 'Queued to attended, today', 'minutes');
        }

        if ($has('appointments')) {
            $add('appointments_today', 'Appointments today', $this->appointmentsToday()->count(), 'CalendarDays', '/appointments', 'Scheduled and checked in');
        }

        if ($has('laboratory')) {
            $add('lab_awaiting_collection', 'Awaiting collection', LabOrder::query()->where('status', LabOrderStatus::Ordered->value)->count(), 'FlaskConical', '/laboratory?status=ordered', 'Specimens to collect');
            $add('lab_in_progress', 'In the lab', LabOrder::query()->whereIn('status', [LabOrderStatus::Collected->value, LabOrderStatus::InProgress->value])->count(), 'Microscope', '/laboratory', 'Received or being processed');
            $add('lab_completed_today', 'Released today', LabOrder::query()->where('status', LabOrderStatus::Completed->value)->whereBetween('verified_at', $this->today())->count(), 'ClipboardCheck', '/laboratory?status=completed', 'Results verified');
        }

        if ($has('pharmacy')) {
            $add('pharmacy_waiting', 'Waiting at pharmacy', $this->waitingAt('pharmacy')->count(), 'Pill', '/pharmacy', 'Prescriptions to dispense');
            $add('dispensed_today', 'Dispensed today', $this->dispensedToday(), 'PackageCheck', '/pharmacy', 'Prescriptions filled');
        }

        if ($has('admissions')) {
            $add('admitted_now', 'On the wards', $this->admittedNow(), 'BedDouble', '/admissions', 'Inpatients right now');
            [$occupied, $total] = $this->bedCounts();
            $add('bed_occupancy', 'Bed occupancy', $total > 0 ? (int) round($occupied / $total * 100) : 0, 'BedDouble', '/admissions', "{$occupied} of {$total} beds", 'percent');
            $add('pending_admissions', 'Awaiting a bed', Admission::query()->where('status', AdmissionStatus::Pending->value)->count(), 'BedSingle', '/admissions', 'Admission requests');
        }

        if ($has('billing')) {
            $add('collected_today', 'Collected today', $this->collectedToday(), 'Banknote', '/billing', 'All cashiers', 'money');
            $add('my_till_today', 'My till today', $this->collectedToday($user), 'Wallet', '/billing', 'Received by you', 'money');
            $add('outstanding', 'Outstanding balances', round($this->reports->outstandingBalance(), 2), 'ReceiptText', '/billing', 'Unpaid across open bills', 'money');
        }

        if ($has('claims')) {
            $add('claims_draft', 'Claims to submit', Claim::query()->where('status', ClaimStatus::Draft->value)->count(), 'HandCoins', '/claims?status=draft', 'Still in draft');
            $add('claims_receivable', 'Awaiting remittance', $this->claimsReceivable(), 'Landmark', '/claims?status=submitted', 'Submitted, not yet paid', 'money');
        }

        if ($has('inventory')) {
            $add('low_stock', 'Low stock', $this->lowStockCount(), 'Package', '/inventory', 'At or below reorder level');
            $add('expiring_soon', 'Expiring soon', $this->expiringBatchCount(), 'CalendarClock', '/inventory', 'Batches within '.self::EXPIRY_WINDOW_DAYS.' days');
        }

        $order = $has('reports')
            ? [...self::MANAGEMENT_TILES, ...$tiles->keys()->all()]
            : $tiles->keys()->all();

        return array_values(collect($order)
            ->unique()
            ->map(fn (string $key) => $tiles->get($key))
            ->filter()
            ->take(self::TILE_LIMIT)
            ->all());
    }

    // --------------------------------------------------------------- Sections

    /**
     * Waiting and in-service counts per active service point.
     *
     * @return array<string, mixed>
     */
    private function queues(): array
    {
        $points = ServicePoint::active()
            ->withCount([
                'queueEntries as waiting_count' => fn (Builder $q) => $q->where('status', QueueStatus::Waiting->value),
                'queueEntries as in_service_count' => fn (Builder $q) => $q->where('status', QueueStatus::InService->value),
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ServicePoint $point) => [
                'slug' => $point->slug,
                'name' => $point->name,
                'icon' => $point->icon,
                'waiting' => (int) $point->getAttribute('waiting_count'),
                'in_service' => (int) $point->getAttribute('in_service_count'),
                'href' => "/queues/{$point->slug}",
            ])
            ->values()
            ->all();

        return ['points' => $points, 'href' => '/queues'];
    }

    /**
     * The clinician's own worklist and appointments for today.
     *
     * @return array<string, mixed>
     */
    private function clinical(User $user): array
    {
        return [
            'worklist' => $this->worklist($this->waitingAt('clinical', $user), 'clinical'),
            'waiting_count' => $this->waitingAt('clinical', $user)->count(),
            'appointments' => $this->appointmentRows($this->appointmentsToday($user)),
            'seen_today' => $this->signedTodayBy($user),
            'href' => '/clinical',
        ];
    }

    /**
     * Patients waiting at the nursing service points.
     *
     * @return array<string, mixed>
     */
    private function nursing(): array
    {
        return [
            'worklist' => $this->worklist($this->waitingAt('nursing'), 'nursing'),
            'waiting_count' => $this->waitingAt('nursing')->count(),
            'href' => '/nursing',
        ];
    }

    /**
     * Today's appointment book, for front-desk roles without a consultation queue.
     *
     * @return array<string, mixed>
     */
    private function appointments(): array
    {
        return [
            'rows' => $this->appointmentRows($this->appointmentsToday()),
            'count' => $this->appointmentsToday()->count(),
            'href' => '/appointments',
        ];
    }

    /**
     * Active requisitions by stage, oldest and most urgent first.
     *
     * @return array<string, mixed>
     */
    private function laboratory(): array
    {
        $active = LabOrder::query()
            ->whereIn('status', [LabOrderStatus::Ordered->value, LabOrderStatus::Collected->value, LabOrderStatus::InProgress->value])
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->get();

        $counts = collect([LabOrderStatus::Ordered, LabOrderStatus::Collected, LabOrderStatus::InProgress])
            ->map(fn (LabOrderStatus $status) => [
                'key' => $status->value,
                'label' => $status->label(),
                'value' => $active->where('status', $status)->count(),
                'href' => "/laboratory?status={$status->value}",
            ])
            ->values()
            ->all();

        $worklist = $active
            ->sortBy(fn (LabOrder $order) => $this->priorityRank($order->priority) * 10_000_000_000 + ($order->created_at?->getTimestamp() ?? 0))
            ->take(self::LIST_LIMIT)
            ->map(fn (LabOrder $order) => [
                'id' => $order->id,
                'accession_number' => $order->accession_number,
                'patient' => $order->patient->fullName(),
                'file_number' => $order->patient->file_number,
                'priority' => $order->priority->value,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'age' => $this->elapsed($order->created_at),
                'href' => "/laboratory/{$order->id}",
            ])
            ->values()
            ->all();

        return ['counts' => $counts, 'worklist' => $worklist, 'active_count' => $active->count(), 'href' => '/laboratory'];
    }

    /**
     * Prescriptions waiting to be dispensed.
     *
     * @return array<string, mixed>
     */
    private function pharmacy(): array
    {
        return [
            'worklist' => $this->worklist($this->waitingAt('pharmacy'), 'pharmacy'),
            'waiting_count' => $this->waitingAt('pharmacy')->count(),
            'dispensed_today' => $this->dispensedToday(),
            'href' => '/pharmacy',
        ];
    }

    /**
     * Bed state per ward and the admission requests still waiting for a bed.
     *
     * @return array<string, mixed>
     */
    private function admissions(): array
    {
        $wards = Ward::query()
            ->where('is_active', true)
            ->with('beds')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Ward $ward) => [
                'id' => $ward->id,
                'name' => $ward->name,
                'code' => $ward->code,
                'occupied' => $ward->beds->where('status', BedStatus::Occupied)->count(),
                'available' => $ward->beds->where('status', BedStatus::Available)->count(),
                'out_of_service' => $ward->beds->where('status', BedStatus::OutOfService)->count(),
                'href' => "/admissions/wards/{$ward->id}",
            ])
            ->values()
            ->all();

        $pending = Admission::query()
            ->where('status', AdmissionStatus::Pending->value)
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->orderBy('created_at')
            ->get();

        return [
            'wards' => $wards,
            'admitted_now' => $this->admittedNow(),
            'pending_count' => $pending->count(),
            'pending' => $pending
                ->take(5)
                ->map(fn (Admission $admission) => [
                    'id' => $admission->id,
                    'patient' => $admission->patient->fullName(),
                    'file_number' => $admission->patient->file_number,
                    'diagnosis' => $admission->admitting_diagnosis,
                    'age' => $this->elapsed($admission->created_at),
                    'href' => "/admissions/{$admission->id}",
                ])
                ->values()
                ->all(),
            'href' => '/admissions',
        ];
    }

    /**
     * Today's takings and the bills still carrying a balance.
     *
     * @return array<string, mixed>
     */
    private function billing(User $user): array
    {
        $unpaidQuery = Bill::query()
            ->whereIn('status', [BillStatus::Open->value, BillStatus::PartiallyPaid->value]);

        $unpaid = (clone $unpaidQuery)
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->withSum('charges as charges_total', 'total')
            ->withSum('payments as payments_total', 'amount')
            ->latest()
            ->take(self::LIST_LIMIT)
            ->get()
            ->map(function (Bill $bill) {
                $total = (float) ($bill->charges_total ?? 0);
                $paid = (float) ($bill->payments_total ?? 0);

                return [
                    'id' => $bill->id,
                    'patient' => $bill->patient->fullName(),
                    'file_number' => $bill->patient->file_number,
                    'status' => $bill->status->value,
                    'total' => round($total, 2),
                    'paid' => round($paid, 2),
                    'balance' => round(max(0, $total - $paid), 2),
                    'age' => $this->elapsed($bill->created_at),
                    'href' => "/billing/{$bill->id}",
                ];
            })
            ->values()
            ->all();

        $todayPayments = Payment::query()
            ->whereBetween('created_at', $this->today())
            ->get(['amount', 'method', 'received_by']);

        $byMethod = $todayPayments->groupBy(fn (Payment $p) => $p->method->value);

        return [
            'unpaid' => $unpaid,
            'unpaid_count' => $unpaidQuery->count(),
            'collected_today' => round((float) $todayPayments->sum('amount'), 2),
            'my_collected_today' => round((float) $todayPayments->where('received_by', $user->id)->sum('amount'), 2),
            'by_method' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $m) => [
                    'label' => $m->label(),
                    'value' => round((float) ($byMethod->get($m->value)?->sum('amount') ?? 0), 2),
                ])
                ->filter(fn (array $row) => $row['value'] > 0)
                ->values()
                ->all(),
            'href' => '/billing',
        ];
    }

    /**
     * Claims still in draft or awaiting remittance, and what each payer owes.
     *
     * @return array<string, mixed>
     */
    private function claims(): array
    {
        $open = Claim::query()
            ->whereIn('status', [ClaimStatus::Draft->value, ClaimStatus::Submitted->value, ClaimStatus::PartiallyPaid->value])
            ->with(['patient:id,file_number,surname,first_name,other_names', 'payer:id,name'])
            ->get();

        $receivable = $open->whereIn('status', [ClaimStatus::Submitted, ClaimStatus::PartiallyPaid]);

        return [
            'draft_count' => $open->where('status', ClaimStatus::Draft)->count(),
            'receivable_count' => $receivable->count(),
            'receivable_amount' => round((float) $receivable->sum(fn (Claim $c) => max(0, $c->payer_amount - $c->paid_amount)), 2),
            'awaiting' => $receivable
                ->sortBy(fn (Claim $c) => ($c->submitted_at ?? $c->created_at)?->getTimestamp() ?? 0)
                ->take(6)
                ->map(fn (Claim $c) => [
                    'id' => $c->id,
                    'claim_number' => $c->claim_number,
                    'patient' => $c->patient->fullName(),
                    'payer' => $c->payer->name,
                    'amount' => round(max(0, $c->payer_amount - $c->paid_amount), 2),
                    'age' => $this->elapsed($c->submitted_at ?? $c->created_at),
                    'href' => "/claims/{$c->id}",
                ])
                ->values()
                ->all(),
            'by_payer' => $receivable
                ->groupBy(fn (Claim $c) => $c->payer->name)
                ->map(fn (Collection $rows, string $payer) => [
                    'label' => $payer,
                    'value' => round((float) $rows->sum(fn (Claim $c) => max(0, $c->payer_amount - $c->paid_amount)), 2),
                ])
                ->sortByDesc('value')
                ->take(5)
                ->values()
                ->all(),
            'href' => '/claims',
        ];
    }

    /**
     * Month-to-date headline figures for management, with the way into the
     * executive overview where the trends live.
     *
     * @return array<string, mixed>
     */
    private function management(): array
    {
        $from = Carbon::today()->startOfMonth();
        $to = Carbon::today()->endOfDay();

        return [
            'period' => Carbon::today()->isoFormat('MMMM YYYY'),
            'visits' => Visit::query()->whereBetween('opened_at', [$from, $to])->count(),
            'new_patients' => Patient::query()->whereBetween('created_at', [$from, $to])->count(),
            'consultations' => Encounter::consultations()->signed()->whereBetween('signed_at', [$from, $to])->count(),
            'revenue' => round((float) Payment::query()->whereBetween('created_at', [$from, $to])->sum('amount'), 2),
            'outstanding' => round($this->reports->outstandingBalance(), 2),
            'overview_href' => '/reports/overview',
            'reports_href' => '/reports',
        ];
    }

    // ----------------------------------------------------------------- Alerts

    /**
     * Conditions that need someone to act, limited to modules the user can
     * reach and to counts above zero.
     *
     * @param  array<int, string>  $modules
     * @return list<array<string, mixed>>
     */
    private function alerts(array $modules): array
    {
        $has = fn (string ...$slugs): bool => array_intersect($slugs, $modules) !== [];
        $alerts = [];

        $alert = function (string $key, string $label, string $sub, int $count, string $tone, string $href) use (&$alerts): void {
            if ($count > 0) {
                $alerts[] = compact('key', 'label', 'sub', 'count', 'tone', 'href');
            }
        };

        if ($has('queues')) {
            $longWaits = QueueEntry::query()
                ->where('status', QueueStatus::Waiting->value)
                ->where('queued_at', '<=', now()->subMinutes(self::LONG_WAIT_MINUTES))
                ->count();
            $alert('long_waits', 'Waiting over an hour', 'Patients queued for more than '.self::LONG_WAIT_MINUTES.' minutes', $longWaits, 'red', '/queues');
        }

        if ($has('laboratory')) {
            $unverified = LabOrder::query()->where('status', LabOrderStatus::InProgress->value)->count();
            $alert('results_to_verify', 'Results awaiting verification', 'Entered but not yet released', $unverified, 'violet', '/laboratory?status=in_progress');
        }

        if ($has('appointments')) {
            $noShows = Appointment::query()
                ->whereBetween('scheduled_start', $this->today())
                ->where('status', AppointmentStatus::NoShow->value)
                ->count();
            $alert('no_shows_today', 'No-shows today', 'Patients to call back', $noShows, 'amber', '/appointments');
        }

        if ($has('admissions')) {
            $pending = Admission::query()->where('status', AdmissionStatus::Pending->value)->count();
            $alert('pending_admissions', 'Admissions awaiting a bed', 'Requests not yet placed on a ward', $pending, 'blue', '/admissions');
        }

        if ($has('billing')) {
            $overdue = Bill::query()
                ->whereIn('status', [BillStatus::Open->value, BillStatus::PartiallyPaid->value])
                ->where('created_at', '<=', now()->subDays(self::OVERDUE_BILL_DAYS))
                ->count();
            $alert('overdue_bills', 'Bills unpaid over '.self::OVERDUE_BILL_DAYS.' days', 'Balances still open', $overdue, 'amber', '/billing');
        }

        if ($has('claims')) {
            $stale = Claim::query()
                ->where('status', ClaimStatus::Draft->value)
                ->where('created_at', '<=', now()->subDays(self::STALE_CLAIM_DAYS))
                ->count();
            $alert('stale_claims', 'Claims not yet submitted', 'In draft for more than '.self::STALE_CLAIM_DAYS.' days', $stale, 'amber', '/claims?status=draft');
        }

        if ($has('surveillance')) {
            $pending = SurveillanceCase::query()->awaitingNotification()->count();
            $alert('notifiable_cases', 'Notifiable cases awaiting DSNO', 'IDSR cases to notify within 24 hours', $pending, 'red', '/surveillance?status=pending');
        }

        if ($has('inventory', 'pharmacy')) {
            $alert('expiring_batches', 'Batches expiring soon', 'Stock expiring within '.self::EXPIRY_WINDOW_DAYS.' days', $this->expiringBatchCount(), 'red', '/inventory');
            $alert('low_stock', 'Low stock items', 'At or below reorder level', $this->lowStockCount(), 'amber', '/inventory');
        }

        return $alerts;
    }

    // ---------------------------------------------------------------- Queries

    /**
     * Entries waiting at the service points of a module, optionally limited to
     * those a clinician can pick up (unassigned or assigned to them).
     *
     * @return Builder<QueueEntry>
     */
    private function waitingAt(string $moduleSlug, ?User $forUser = null): Builder
    {
        return QueueEntry::query()
            ->where('status', QueueStatus::Waiting->value)
            ->whereIn('service_point_id', ServicePoint::active()->where('module_slug', $moduleSlug)->select('id'))
            ->when($forUser, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereNull('assigned_to')
                ->orWhere('assigned_to', $forUser->id)));
    }

    /**
     * Turn waiting entries into worklist rows: emergencies first, then the
     * longest wait.
     *
     * @param  Builder<QueueEntry>  $query
     * @return list<array<string, mixed>>
     */
    private function worklist(Builder $query, string $console): array
    {
        return array_values($query
            ->with(['patient:id,file_number,surname,first_name,other_names', 'servicePoint:id,name'])
            ->get()
            ->sortBy(fn (QueueEntry $e) => $this->priorityRank($e->priority) * 10_000_000_000 + ($e->queued_at?->getTimestamp() ?? 0))
            ->take(self::LIST_LIMIT)
            ->map(fn (QueueEntry $e) => [
                'id' => $e->id,
                'patient' => $e->patient->fullName(),
                'file_number' => $e->patient->file_number,
                'service_point' => $e->servicePoint->name,
                'priority' => $e->priority->value,
                'waited' => $this->elapsed($e->queued_at),
                'href' => "/{$console}/{$e->id}",
            ])
            ->all());
    }

    /**
     * Appointments booked for today that have not been completed or cancelled.
     *
     * @return Builder<Appointment>
     */
    private function appointmentsToday(?User $provider = null): Builder
    {
        return Appointment::query()
            ->whereBetween('scheduled_start', $this->today())
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::CheckedIn->value])
            ->when($provider, fn (Builder $q) => $q->where('provider_id', $provider->id));
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return list<array<string, mixed>>
     */
    private function appointmentRows(Builder $query): array
    {
        return array_values($query
            ->with(['patient:id,file_number,surname,first_name,other_names', 'provider:id,name', 'servicePoint:id,name'])
            ->orderBy('scheduled_start')
            ->take(self::LIST_LIMIT)
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'time' => $a->scheduled_start->format('H:i'),
                'patient' => $a->patient->fullName(),
                'file_number' => $a->patient->file_number,
                'provider' => $a->provider?->name,
                'service_point' => $a->servicePoint->name,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'href' => '/appointments',
            ])
            ->all());
    }

    private function signedTodayBy(User $user): int
    {
        return Encounter::consultations()->signed()
            ->where('author_id', $user->id)
            ->whereBetween('signed_at', $this->today())
            ->count();
    }

    private function admittedNow(): int
    {
        return Admission::query()->where('status', AdmissionStatus::Admitted->value)->count();
    }

    /**
     * Occupied beds and beds in service (occupied plus available).
     *
     * @return array{0: int, 1: int}
     */
    private function bedCounts(): array
    {
        $beds = Ward::query()->where('is_active', true)->with('beds')->get()->flatMap->beds;

        $occupied = $beds->where('status', BedStatus::Occupied)->count();
        $available = $beds->where('status', BedStatus::Available)->count();

        return [$occupied, $occupied + $available];
    }

    private function dispensedToday(): int
    {
        return Dispense::query()
            ->where('status', Dispense::STATUS_DISPENSED)
            ->whereBetween('created_at', $this->today())
            ->count();
    }

    private function collectedToday(?User $receivedBy = null): float
    {
        return round((float) Payment::query()
            ->whereBetween('created_at', $this->today())
            ->when($receivedBy, fn (Builder $q) => $q->where('received_by', $receivedBy->id))
            ->sum('amount'), 2);
    }

    private function claimsReceivable(): float
    {
        return round((float) Claim::query()
            ->whereIn('status', [ClaimStatus::Submitted->value, ClaimStatus::PartiallyPaid->value])
            ->get(['payer_amount', 'paid_amount'])
            ->sum(fn (Claim $c) => max(0, $c->payer_amount - $c->paid_amount)), 2);
    }

    private function lowStockCount(): int
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->count();
    }

    private function expiringBatchCount(): int
    {
        return StockBatch::query()
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [Carbon::today(), Carbon::today()->addDays(self::EXPIRY_WINDOW_DAYS)])
            ->count();
    }

    /**
     * Average minutes from joining a queue to being attended, for entries
     * attended today.
     */
    private function averageWaitMinutesToday(): int
    {
        $entries = QueueEntry::query()
            ->whereBetween('started_at', $this->today())
            ->whereNotNull('queued_at')
            ->get(['queued_at', 'started_at']);

        if ($entries->isEmpty()) {
            return 0;
        }

        return (int) round($entries->avg(fn (QueueEntry $e) => $e->queued_at->diffInSeconds($e->started_at)) / 60);
    }

    // ---------------------------------------------------------------- Helpers

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function today(): array
    {
        return [Carbon::today(), Carbon::today()->endOfDay()];
    }

    private function priorityRank(Priority $priority): int
    {
        return match ($priority) {
            Priority::Emergency => 0,
            Priority::Urgent => 1,
            Priority::Normal => 2,
        };
    }

    /**
     * A short "how long ago" for worklists: 12 min, 3 h, 2 d.
     */
    private function elapsed(?CarbonInterface $since): string
    {
        if ($since === null) {
            return '—';
        }

        $minutes = (int) abs($since->diffInMinutes(now()));

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        if ($minutes < 60 * 24) {
            return intdiv($minutes, 60).' h';
        }

        return intdiv($minutes, 60 * 24).' d';
    }
}
