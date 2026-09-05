<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PrescriptionOverfilledException;
use App\Models\Dispense;
use App\Models\InventoryItem;
use App\Models\Medication;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\StockLocation;
use App\Services\PharmacyService;
use App\Services\StockLocations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyController extends Controller
{
    private const MODULE = 'pharmacy';

    public function __construct(
        private readonly PharmacyService $pharmacy,
        private readonly StockLocations $locations,
    ) {}

    /**
     * The dispensing worklist: patients waiting at pharmacy.
     */
    public function index(Request $request): Response
    {
        $entries = QueueEntry::query()
            ->whereIn('service_point_id', $this->pharmacyServicePointIds())
            ->active()
            ->worklistOrder()
            ->with(['patient:id,file_number,surname,first_name,other_names,sex,date_of_birth', 'servicePoint:id,name'])
            ->get()
            ->map(fn (QueueEntry $entry) => [
                'id' => $entry->id,
                'priority' => $entry->priority->value,
                'priority_label' => $entry->priority->label(),
                'service_point' => $entry->servicePoint->name,
                'waiting_since' => $entry->queued_at?->diffForHumans(short: true),
                'pending_scripts' => $entry->patient->medications()->outstanding()->count(),
                'url' => route('pharmacy.dispense', $entry),
                'patient' => [
                    'name' => $entry->patient->fullName(),
                    'initials' => $entry->patient->initials(),
                    'file_number' => $entry->patient->file_number,
                    'sex' => $entry->patient->sex,
                    'age' => $entry->patient->age(),
                ],
            ]);

        $recent = Dispense::query()
            ->where('dispensed_by', $request->user()->id)
            ->latest()
            ->take(8)
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->withCount('items')
            ->get()
            ->map(fn (Dispense $d) => [
                'id' => $d->id,
                'patient_name' => $d->patient->fullName(),
                'items_count' => $d->items_count,
                'total' => $d->total(),
                'at' => $d->created_at?->diffForHumans(short: true),
            ]);

        return Inertia::render('pharmacy/Index', [
            'queue' => $entries,
            'recent' => $recent,
        ]);
    }

    /**
     * The dispensing screen for a patient at pharmacy.
     */
    public function show(Request $request, QueueEntry $entry): Response
    {
        $this->authorizePharmacy($entry);
        $entry->load(['patient', 'servicePoint:id,name']);
        $patient = $entry->patient;
        $location = $this->locations->forServicePoint($entry->servicePoint);

        $catalog = $this->catalogAt($location);

        $prescriptions = $patient->medications()->active()->with('product')->get()
            ->sortBy(fn (Medication $m) => [$m->fulfilment->isOutstanding() ? 0 : 1, -$m->id])
            ->values()
            ->map(fn (Medication $m) => [
                'id' => $m->id,
                'label' => $m->label(),
                'name' => $m->name,
                'dose' => $m->dose,
                'frequency' => $m->frequency,
                'route' => $m->route,
                'quantity' => $m->quantity,
                'duration_days' => $m->duration_days,
                'quantity_dispensed' => $m->quantity_dispensed,
                'remaining' => $m->quantityRemaining(),
                'outstanding' => $m->fulfilment->isOutstanding(),
                'fulfilment' => [
                    'value' => $m->fulfilment->value,
                    'label' => $m->fulfilment->label(),
                    'tone' => $m->fulfilment->tone(),
                ],
                'prescribed_at' => $m->created_at?->isoFormat('D MMM YYYY'),
                'products' => $this->pharmacy->productsFor($m)
                    ->map(fn (InventoryItem $i) => $catalog->firstWhere('id', $i->id))
                    ->filter()
                    ->values()
                    ->all(),
            ]);

        return Inertia::render('pharmacy/Dispense', [
            'entry' => ['id' => $entry->id, 'service_point' => $entry->servicePoint->name],
            'location' => ['id' => $location->id, 'name' => $location->name],
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->fullName(),
                'initials' => $patient->initials(),
                'file_number' => $patient->file_number,
                'sex_label' => $patient->sex === 'F' ? 'Female' : 'Male',
                'age' => $patient->age(),
                'url' => route('patients.show', $patient->id),
            ],
            'prescriptions' => $prescriptions,
            'catalog' => $catalog->values(),
            'dispensed' => $patient->dispenses()->latest()->with('items')->take(10)->get()->map(fn (Dispense $d) => [
                'id' => $d->id,
                'at' => $d->created_at?->isoFormat('D MMM YYYY, h:mm a'),
                'total' => $d->total(),
                'items' => $d->items->map(fn ($it) => [
                    'name' => $it->name,
                    'quantity' => $it->quantity,
                    'total' => $it->total,
                ])->all(),
            ]),
        ]);
    }

    /**
     * Dispense items from stock, pricing them onto the patient's bill and
     * marking the prescriptions they fill.
     */
    public function dispense(Request $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizePharmacy($entry);
        $entry->loadMissing('servicePoint');
        $location = $this->locations->forServicePoint($entry->servicePoint);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.medication_id' => ['nullable', Rule::exists('medications', 'id')->where('patient_id', $entry->patient_id)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Pre-flight stock check for friendly errors.
        foreach ($data['lines'] as $i => $line) {
            $item = InventoryItem::find((int) $line['inventory_item_id']);
            if ($item && $item->dispensableQuantity($location) < (int) $line['quantity']) {
                throw ValidationException::withMessages([
                    "lines.{$i}.quantity" => "Only {$item->dispensableQuantity($location)} {$item->unit} of {$item->name} at {$location->name}.",
                ]);
            }
        }

        try {
            $this->pharmacy->dispense(
                patient: $entry->patient,
                visit: $entry->visit,
                encounter: null,
                queueEntry: $entry,
                lines: $data['lines'],
                actor: $request->user(),
                note: $data['note'] ?? null,
                from: $location,
            );
        } catch (InsufficientStockException|PrescriptionOverfilledException $e) {
            throw ValidationException::withMessages(['lines' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dispensed and billed.']);

        return back();
    }

    /**
     * Note that a prescription will be obtained outside the facility.
     */
    public function markExternal(Request $request, QueueEntry $entry, Medication $medication): RedirectResponse
    {
        $this->authorizePharmacy($entry);
        abort_unless($medication->patient_id === $entry->patient_id, 404);

        $this->pharmacy->markExternal($medication, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marked as sourced outside.']);

        return back();
    }

    /**
     * Put an externally sourced prescription back on the list.
     */
    public function reopen(Request $request, QueueEntry $entry, Medication $medication): RedirectResponse
    {
        $this->authorizePharmacy($entry);
        abort_unless($medication->patient_id === $entry->patient_id, 404);

        $this->pharmacy->reopen($medication);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Back on the dispensing list.']);

        return back();
    }

    /**
     * The active catalogue with the quantity held at the dispensing location.
     *
     * @return Collection<int, array{id: int, label: string, name: string, unit: string, selling_price: float, quantity_on_hand: int, is_low: bool}>
     */
    private function catalogAt(StockLocation $location): Collection
    {
        return InventoryItem::query()
            ->active()
            ->withSum(['batches as at_location' => fn ($q) => $q
                ->where('stock_location_id', $location->id)
                ->where(fn ($w) => $w->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString())),
            ], 'quantity')
            ->get()
            ->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'label' => $i->label(),
                'name' => $i->name,
                'unit' => $i->unit,
                'selling_price' => $i->selling_price,
                'quantity_on_hand' => (int) ($i->at_location ?? 0),
                'is_low' => $i->isLowStock(),
            ]);
    }

    /**
     * Ensure the entry is a pharmacy queue entry.
     */
    private function authorizePharmacy(QueueEntry $entry): void
    {
        $entry->loadMissing('servicePoint');
        abort_unless($entry->servicePoint->module_slug === self::MODULE, 404);
    }

    /**
     * @return array<int, int>
     */
    private function pharmacyServicePointIds(): array
    {
        return ServicePoint::active()->where('module_slug', self::MODULE)->pluck('id')->all();
    }
}
