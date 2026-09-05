<?php

namespace App\Http\Controllers;

use App\Enums\InventoryCategory;
use App\Enums\Permission;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\StockBatch;
use App\Models\StockLocation;
use App\Services\InventoryService;
use App\Services\StockLocations;
use App\Services\StockReorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StockLocations $locations,
        private readonly StockReorder $reorder,
    ) {}

    /**
     * The store: stock levels with low-stock and expiry alerts, for the
     * whole facility or one location.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $locations = $this->locations->all();
        $location = $request->filled('location')
            ? $locations->firstWhere('slug', $request->string('location')->toString())
            : null;

        $items = InventoryItem::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->withMin('dispensableBatches as next_expiry', 'expiry_date')
            ->when($location, fn ($q) => $q->withSum(
                ['batches as at_location' => fn ($b) => $b->where('stock_location_id', $location->id)],
                'quantity',
            ))
            ->get()
            ->map(fn (InventoryItem $item) => [
                ...$this->itemCard($item),
                'at_location' => $location ? (int) ($item->at_location ?? 0) : null,
            ]);

        return Inertia::render('inventory/Index', [
            'items' => $items,
            'filters' => ['q' => $search, 'location' => $location?->slug],
            'counts' => [
                'total' => InventoryItem::count(),
                'low_stock' => InventoryItem::query()->active()->lowStock()->count(),
            ],
            'categories' => collect(InventoryCategory::cases())->map(fn (InventoryCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'locations' => $locations->map(fn (StockLocation $l) => $this->locationCard($l)),
            'reorder' => $this->reorder->settings(),
            'can' => [
                'manage_settings' => (bool) $request->user()?->hasPermission(Permission::ManageInventorySettings),
            ],
        ]);
    }

    /**
     * A single item: its batches by location and recent stock movements.
     */
    public function show(InventoryItem $item): Response
    {
        $item->load([
            'batches' => fn ($q) => $q->with('location:id,name')->orderByRaw('expiry_date is null')->orderBy('expiry_date'),
            'movements' => fn ($q) => $q->with('location:id,name')->limit(30),
        ]);

        $locations = $this->locations->all();

        return Inertia::render('inventory/Item', [
            'item' => [
                ...$this->itemCard($item),
                'cost_price' => $item->cost_price,
            ],
            'locations' => $locations->map(fn (StockLocation $l) => [
                ...$this->locationCard($l),
                'on_hand' => $item->quantityAt($l),
            ]),
            'receiving_location_id' => $this->locations->receiving()->id,
            'batches' => $item->batches->map(fn (StockBatch $b) => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'expiry_date' => $b->expiry_date?->isoFormat('D MMM YYYY'),
                'quantity' => $b->quantity,
                'is_expired' => $b->isExpired(),
                'location' => $b->location?->name,
            ]),
            'movements' => $item->movements->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->label(),
                'quantity_change' => $m->quantity_change,
                'reason' => $m->reason,
                'location' => $m->location?->name,
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
     * Receive stock into a new batch at a location.
     */
    public function receive(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_location_id' => ['nullable', 'integer', Rule::exists('stock_locations', 'id')->where('is_active', true)],
        ]);

        $location = isset($data['stock_location_id']) ? StockLocation::find((int) $data['stock_location_id']) : null;

        $this->inventory->receiveStock($item, $data['quantity'], $data, $request->user(), $location);

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
     * Stocked products for the prescribing form: those of a drug concept,
     * or those matching a typed name.
     */
    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'concept_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        if (empty($data['concept_id']) && $q === '') {
            return response()->json(['products' => []]);
        }

        $products = InventoryItem::query()
            ->active()
            ->where('category', InventoryCategory::Drug->value)
            ->when(! empty($data['concept_id']), fn ($query) => $query->where('concept_id', (int) $data['concept_id']))
            ->when(empty($data['concept_id']), fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->limit(20)
            ->get()
            ->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'label' => $i->label(),
                'name' => $i->name,
                'unit' => $i->unit,
                'selling_price' => $i->selling_price,
                'quantity_on_hand' => $i->quantity_on_hand,
                'is_low' => $i->isLowStock(),
            ]);

        return response()->json(['products' => $products]);
    }

    /**
     * Set the reorder planning parameters.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_cover_months' => ['required', 'numeric', 'min:0', 'max:24'],
            'max_cover_months' => ['required', 'numeric', 'min:0', 'max:36', 'gte:min_cover_months'],
            'consumption_window_days' => ['required', 'integer', 'min:7', 'max:730'],
        ]);

        $this->reorder->save($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reorder settings saved.']);

        return back();
    }

    /**
     * Replace every consuming item's reorder level with its suggestion.
     */
    public function applyReorderLevels(): RedirectResponse
    {
        $updated = $this->reorder->applySuggestedLevels();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Reorder levels updated on {$updated} item(s)."]);

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

    /**
     * @return array{id: int, slug: string, name: string, kind: string}
     */
    private function locationCard(StockLocation $location): array
    {
        return [
            'id' => $location->id,
            'slug' => $location->slug,
            'name' => $location->name,
            'kind' => $location->kind->label(),
        ];
    }
}
