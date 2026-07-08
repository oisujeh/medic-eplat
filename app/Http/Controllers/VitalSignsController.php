<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVitalSignsRequest;
use App\Models\QueueEntry;
use App\Models\VitalSign;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VitalSignsController extends Controller
{
    /**
     * Record a set of vitals / anthropometrics against a queue entry's visit.
     */
    public function store(StoreVitalSignsRequest $request, QueueEntry $entry): RedirectResponse
    {
        $entry->loadMissing('servicePoint');

        abort_unless(
            (bool) $request->user()?->canAccessModule($entry->servicePoint->module_slug ?? ''),
            403,
        );
        abort_unless($entry->status->isActive(), 422);

        $data = $request->validated();

        VitalSign::create([
            ...$data,
            'visit_id' => $entry->visit_id,
            'patient_id' => $entry->patient_id,
            'queue_entry_id' => $entry->id,
            'service_point_id' => $entry->service_point_id,
            'recorded_by' => $request->user()->id,
            'bmi' => $this->calculateBmi($data['weight_kg'] ?? null, $data['height_cm'] ?? null),
            'recorded_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vitals recorded.']);

        return back();
    }

    /**
     * Body Mass Index (kg/m²) rounded to one decimal, or null when incomplete.
     */
    private function calculateBmi(int|float|string|null $weightKg, int|float|string|null $heightCm): ?float
    {
        if (! $weightKg || ! $heightCm) {
            return null;
        }

        $heightM = (float) $heightCm / 100;

        if ($heightM <= 0) {
            return null;
        }

        return round((float) $weightKg / ($heightM ** 2), 1);
    }
}
