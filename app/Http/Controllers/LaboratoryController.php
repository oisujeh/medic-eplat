<?php

namespace App\Http\Controllers;

use App\Enums\LabDepartment;
use App\Enums\LabOrderStatus;
use App\Enums\Priority;
use App\Enums\ResultFlag;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Services\LabWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LaboratoryController extends Controller
{
    public function __construct(private readonly LabWorkflowService $lab) {}

    /**
     * The laboratory worklist: requisitions to be collected, analysed and released.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status', 'active')->toString();
        $department = $request->string('department')->toString();
        $search = $request->string('q')->toString();

        $orders = LabOrder::query()
            ->when($status === 'active', fn ($q) => $q->active())
            ->when(
                in_array($status, array_column(LabOrderStatus::cases(), 'value'), true),
                fn ($q) => $q->where('status', $status),
            )
            ->when($department !== '', fn ($q) => $q->whereHas('results', fn ($r) => $r->where('department', $department)))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('accession_number', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p
                    ->where('file_number', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%"))))
            ->worklistOrder()
            ->with(['patient:id,file_number,surname,first_name,other_names,sex,date_of_birth', 'orderedBy:id,name', 'results:id,lab_order_id,department,status'])
            ->limit(60)
            ->get()
            ->map(fn (LabOrder $order) => $this->orderCard($order));

        return Inertia::render('laboratory/Index', [
            'orders' => $orders,
            'filters' => ['status' => $status, 'department' => $department, 'q' => $search],
            'counts' => [
                'active' => LabOrder::query()->active()->count(),
                'ordered' => LabOrder::where('status', LabOrderStatus::Ordered->value)->count(),
                'in_progress' => LabOrder::whereIn('status', [LabOrderStatus::Collected->value, LabOrderStatus::InProgress->value])->count(),
                'completed' => LabOrder::where('status', LabOrderStatus::Completed->value)->count(),
            ],
            'departments' => collect(LabDepartment::cases())->map(fn (LabDepartment $d) => [
                'value' => $d->value,
                'label' => $d->label(),
            ]),
        ]);
    }

    /**
     * The order-processing screen for a single requisition.
     */
    public function show(LabOrder $order): Response
    {
        $order->load([
            'patient',
            'orderedBy:id,name',
            'collectedBy:id,name',
            'verifiedBy:id,name',
            'results' => fn ($q) => $q->orderBy('id'),
        ]);

        return Inertia::render('laboratory/Order', [
            'order' => [
                ...$this->orderCard($order),
                'clinical_details' => $order->clinical_details,
                'specimen_type' => $order->specimen_type,
                'ordered_by' => $order->orderedBy?->name,
                'ordered_at' => $order->created_at?->isoFormat('D MMM YYYY, h:mm a'),
                'collected_by' => $order->collectedBy?->name,
                'collected_at' => $order->collected_at?->isoFormat('D MMM YYYY, h:mm a'),
                'received_at' => $order->received_at?->isoFormat('D MMM YYYY, h:mm a'),
                'verified_by' => $order->verifiedBy?->name,
                'verified_at' => $order->verified_at?->isoFormat('D MMM YYYY, h:mm a'),
                'cancelled_reason' => $order->cancelled_reason,
            ],
            'patient' => $this->patientCard($order->patient),
            'results' => $order->results->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'department' => $r->department,
                'specimen' => $r->specimen,
                'value' => $r->value,
                'unit' => $r->unit,
                'reference_range' => $r->reference_range,
                'flag' => $r->flag,
                'status' => $r->status,
                'notes' => $r->notes,
            ]),
            'flags' => collect(ResultFlag::cases())->map(fn (ResultFlag $f) => [
                'value' => $f->value,
                'label' => $f->label(),
            ]),
        ]);
    }

    /**
     * Record specimen collection.
     */
    public function collect(Request $request, LabOrder $order): RedirectResponse
    {
        abort_unless($order->status === LabOrderStatus::Ordered, 422);

        $this->lab->collect(
            $order,
            $request->user(),
            $request->validate(['specimen_type' => ['nullable', 'string', 'max:100']])['specimen_type'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Specimen collected.']);

        return back();
    }

    /**
     * Receive the specimen at the bench.
     */
    public function receive(Request $request, LabOrder $order): RedirectResponse
    {
        abort_unless($order->status === LabOrderStatus::Collected, 422);

        $this->lab->receive($order, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Specimen received — ready for analysis.']);

        return back();
    }

    /**
     * Save preliminary result values (draft — not yet released).
     */
    public function saveResults(Request $request, LabOrder $order): RedirectResponse
    {
        abort_unless($order->status->isActive(), 422);

        $data = $request->validate([
            'results' => ['required', 'array'],
            'results.*.value' => ['nullable', 'string', 'max:255'],
            'results.*.flag' => ['nullable', Rule::enum(ResultFlag::class)],
            'results.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->lab->recordResults($order, $data['results']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Results saved.']);

        return back();
    }

    /**
     * Verify and release the order to the patient's chart.
     */
    public function verify(Request $request, LabOrder $order): RedirectResponse
    {
        abort_unless($order->status->isActive(), 422);
        abort_unless($order->isFullyResulted(), 422, 'Every test needs a value before the order can be released.');

        $this->lab->verify($order, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Results verified and released — {$order->accession_number}."]);

        return to_route('laboratory.index');
    }

    /**
     * Cancel a requisition.
     */
    public function cancel(Request $request, LabOrder $order): RedirectResponse
    {
        abort_unless($order->status->isActive(), 422);

        $this->lab->cancel(
            $order,
            $request->user(),
            $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order cancelled.']);

        return to_route('laboratory.index');
    }

    /**
     * Compact worklist representation of an order.
     *
     * @return array<string, mixed>
     */
    private function orderCard(LabOrder $order): array
    {
        $resulted = $order->results->where('status', 'resulted')->count();

        return [
            'id' => $order->id,
            'accession_number' => $order->accession_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'tone' => $order->status->tone(),
            'priority' => $order->priority->value,
            'priority_label' => $this->priorityLabel($order->priority),
            'test_count' => $order->results->count(),
            'resulted_count' => $resulted,
            'departments' => $order->results->pluck('department')->filter()->unique()->values(),
            'ordered_ago' => $order->created_at?->diffForHumans(short: true),
            'patient' => [
                'name' => $order->patient->fullName(),
                'initials' => $order->patient->initials(),
                'file_number' => $order->patient->file_number,
                'sex' => $order->patient->sex,
                'age' => $order->patient->age(),
            ],
            'url' => route('laboratory.show', $order),
        ];
    }

    /**
     * Patient banner data for the order screen.
     *
     * @return array<string, mixed>
     */
    private function patientCard(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'name' => $patient->fullName(),
            'initials' => $patient->initials(),
            'file_number' => $patient->file_number,
            'sex_label' => $patient->sex === 'F' ? 'Female' : 'Male',
            'age' => $patient->age(),
            'url' => route('patients.show', $patient->id),
        ];
    }

    /**
     * Lab-flavoured priority labels (routine / urgent / STAT).
     */
    private function priorityLabel(Priority $priority): string
    {
        return match ($priority) {
            Priority::Normal => 'Routine',
            Priority::Urgent => 'Urgent',
            Priority::Emergency => 'STAT',
        };
    }
}
