<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\RequisitionStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\RequisitionStateException;
use App\Models\InventoryItem;
use App\Models\StockLocation;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Services\StockLocations;
use App\Services\StockRequisitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Requisitions: one location asking another for stock.
 */
class StockRequisitionController extends Controller
{
    public function __construct(
        private readonly StockRequisitions $requisitions,
        private readonly StockLocations $locations,
    ) {}

    /**
     * The requisition register, open ones first.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $requisitions = StockRequisition::query()
            ->with(['fromLocation:id,name', 'toLocation:id,name', 'requestedBy:id,name'])
            ->withCount('lines')
            ->when($status !== '' && $status !== 'open', fn ($q) => $q->where('status', $status))
            ->when($status === '' || $status === 'open', fn ($q) => $q->open())
            ->orderByRaw("case status when 'requested' then 0 when 'approved' then 1 when 'issued' then 2 else 3 end")
            ->latest('requested_at')
            ->get()
            ->map(fn (StockRequisition $r) => $this->card($r));

        return Inertia::render('inventory/requisitions/Index', [
            'requisitions' => $requisitions,
            'filters' => ['status' => $status === '' ? 'open' : $status],
            'statuses' => collect(RequisitionStatus::cases())->map(fn (RequisitionStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'locations' => $this->locations->all()->map(fn (StockLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'kind' => $l->kind->value,
            ]),
            'default_from_id' => $this->locations->receiving()->id,
            'default_to_id' => $this->locations->dispensary()->id,
            'items' => $this->requisitions->requestableItems()->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'label' => $i->label(),
                'unit' => $i->unit,
                'quantity_on_hand' => $i->quantity_on_hand,
            ]),
        ]);
    }

    /**
     * A requisition with its lines and the actions its status allows.
     */
    public function show(Request $request, StockRequisition $requisition): Response
    {
        $requisition->load([
            'lines.item:id,name,strength,form,unit,quantity_on_hand',
            'fromLocation:id,name', 'toLocation:id,name',
            'requestedBy:id,name', 'approvedBy:id,name', 'issuedBy:id,name', 'receivedBy:id,name',
        ]);

        $available = $this->requisitions->availability($requisition);
        $user = $request->user();

        return Inertia::render('inventory/requisitions/Show', [
            'requisition' => [
                ...$this->card($requisition),
                'note' => $requisition->note,
                'cancel_reason' => $requisition->cancel_reason,
                'approved_by' => $requisition->approvedBy?->name,
                'approved_at' => $requisition->approved_at?->isoFormat('D MMM YYYY, h:mm a'),
                'issued_by' => $requisition->issuedBy?->name,
                'issued_at' => $requisition->issued_at?->isoFormat('D MMM YYYY, h:mm a'),
                'received_by' => $requisition->receivedBy?->name,
                'received_at' => $requisition->received_at?->isoFormat('D MMM YYYY, h:mm a'),
            ],
            'lines' => $requisition->lines->map(fn (StockRequisitionLine $line) => [
                'id' => $line->id,
                'item' => $line->item->label(),
                'unit' => $line->item->unit,
                'quantity_requested' => $line->quantity_requested,
                'quantity_approved' => $line->quantity_approved,
                'quantity_issued' => $line->quantity_issued,
                'quantity_received' => $line->quantity_received,
                'available_at_source' => $available[$line->id] ?? 0,
                'note' => $line->note,
            ]),
            'can' => [
                'approve' => $requisition->status === RequisitionStatus::Requested && $user->hasPermission(Permission::ApproveRequisitions),
                'issue' => $requisition->status === RequisitionStatus::Approved && $user->hasPermission(Permission::IssueStock),
                'receive' => $requisition->status === RequisitionStatus::Issued,
                'cancel' => $requisition->status->isCancellable(),
            ],
        ]);
    }

    /**
     * Raise a requisition.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_location_id' => ['required', 'integer', Rule::exists('stock_locations', 'id')->where('is_active', true)],
            'to_location_id' => ['required', 'integer', 'different:from_location_id', Rule::exists('stock_locations', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $requisition = $this->requisitions->create(
            to: StockLocation::findOrFail((int) $data['to_location_id']),
            from: StockLocation::findOrFail((int) $data['from_location_id']),
            lines: $data['lines'],
            actor: $request->user(),
            note: $data['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Requisition {$requisition->number} raised."]);

        return redirect()->route('inventory.requisitions.show', $requisition);
    }

    /**
     * Approve the quantities to send.
     */
    public function approve(Request $request, StockRequisition $requisition): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->guarded(fn () => $this->requisitions->approve($requisition, $this->lineQuantities($data), $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Requisition approved.']);

        return back();
    }

    /**
     * Issue the approved quantities, moving the stock.
     */
    public function issue(Request $request, StockRequisition $requisition): RedirectResponse
    {
        $this->guarded(fn () => $this->requisitions->issue($requisition, $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock issued.']);

        return back();
    }

    /**
     * Confirm the quantities that arrived.
     */
    public function receive(Request $request, StockRequisition $requisition): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->guarded(fn () => $this->requisitions->receive($requisition, $this->lineQuantities($data), $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Receipt confirmed.']);

        return back();
    }

    /**
     * Withdraw a requisition not yet issued.
     */
    public function cancel(Request $request, StockRequisition $requisition): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->guarded(fn () => $this->requisitions->cancel($requisition, $data['reason'] ?? null, $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Requisition cancelled.']);

        return back();
    }

    /**
     * The per-line quantities posted with an approval or a receipt.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int> line id => quantity
     */
    private function lineQuantities(array $data): array
    {
        $quantities = [];

        foreach ((array) ($data['quantities'] ?? []) as $lineId => $quantity) {
            if ($quantity !== null) {
                $quantities[(int) $lineId] = (int) $quantity;
            }
        }

        return $quantities;
    }

    /**
     * Turn a refused transition or a stock shortfall into a form error.
     */
    private function guarded(callable $action): void
    {
        try {
            $action();
        } catch (RequisitionStateException|InsufficientStockException $e) {
            throw ValidationException::withMessages(['requisition' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function card(StockRequisition $r): array
    {
        return [
            'id' => $r->id,
            'number' => $r->number,
            'status' => ['value' => $r->status->value, 'label' => $r->status->label(), 'tone' => $r->status->tone()],
            'from' => $r->fromLocation?->name,
            'to' => $r->toLocation?->name,
            'lines_count' => $r->lines_count ?? $r->lines->count(),
            'requested_by' => $r->requestedBy?->name,
            'requested_at' => $r->requested_at?->isoFormat('D MMM YYYY, h:mm a'),
            'url' => route('inventory.requisitions.show', $r),
        ];
    }
}
