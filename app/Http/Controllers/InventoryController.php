<?php

namespace App\Http\Controllers;

use App\Enums\InventoryCategory;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\StockBatch;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * The store: stock levels with low-stock and expiry alerts.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $items = InventoryItem::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->withMin('dispensableBatches as next_expiry', 'expiry_date')
            ->get()
            ->map(fn (InventoryItem $item) => $this->itemCard($item));

        return Inertia::render('inventory/Index', [
            'items' => $items,
            'filters' => ['q' => $search],
            'counts' => [
                'total' => InventoryItem::count(),
                'low_stock' => InventoryItem::query()->active()->lowStock()->count(),
            ],
            'categories' => collect(InventoryCategory::cases())->map(fn (InventoryCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    /**
     * A single item: its batches and recent stock movements.
     */
    public function show(InventoryItem $item): Response
    {
        $item->load([
            'batches' => fn ($q) => $q->orderByRaw('expiry_date is null')->orderBy('expiry_date'),
            'movements' => fn ($q) => $q->limit(30),
        ]);

        return Inertia::render('inventory/Item', [
            'item' => [
                ...$this->itemCard($item),
                'cost_price' => $item->cost_price,
            ],
            'batches' => $item->batches->map(fn (StockBatch $b) => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'expiry_date' => $b->expiry_date?->isoFormat('D MMM YYYY'),
                'quantity' => $b->quantity,
                'is_expired' => $b->isExpired(),
            ]),
            'movements' => $item->movements->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->label(),
                'quantity_change' => $m->quantity_change,
                'reason' => $m->reason,
                'at' => $m->created_at?->isoFormat('D MMM YYYY, h:mm a'),
            ]),
        ]);
    }

    /**
     * Add a new item to the catalogue.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:inventory_items,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(InventoryCategory::class)],
            'form' => ['nullable', 'string', 'max:100'],
            'strength' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
        ]);

        InventoryItem::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item added to the catalogue.']);

        return back();
    }

    /**
     * Receive stock into a new batch.
     */
    public function receive(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->inventory->receiveStock($item, $data['quantity'], $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Received {$data['quantity']} × {$item->name}."]);

        return back();
    }

    /**
     * Adjust a batch quantity (stock count, damage, loss).
     */
    public function adjust(Request $request, StockBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->inventory->adjustBatch($batch, $data['delta'], $data['reason'] ?? null, $request->user());
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages(['delta' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock adjusted.']);

        return back();
    }

    /**
     * Compact representation of an item for lists and headers.
     *
     * @return array<string, mixed>
     */
    private function itemCard(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'label' => $item->label(),
            'category' => $item->category->value,
            'unit' => $item->unit,
            'selling_price' => $item->selling_price,
            'quantity_on_hand' => $item->quantity_on_hand,
            'reorder_level' => $item->reorder_level,
            'is_low' => $item->isLowStock(),
            'next_expiry' => isset($item->next_expiry) && $item->next_expiry
                ? Carbon::parse($item->next_expiry)->isoFormat('D MMM YYYY')
                : null,
            'url' => route('inventory.show', $item),
        ];
    }
}
