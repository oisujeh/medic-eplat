<?php

namespace App\Http\Controllers;

use App\Models\NotifiableDisease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The IDSR priority-disease catalogue that drives case detection.
 */
class NotifiableDiseaseController extends Controller
{
    /**
     * The catalogue, immediately notifiable diseases first.
     */
    public function index(): Response
    {
        return Inertia::render('surveillance/Diseases', [
            'diseases' => NotifiableDisease::query()
                ->withCount('cases')
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (NotifiableDisease $disease) => [
                    'id' => $disease->id,
                    'name' => $disease->name,
                    'category' => $disease->category->value,
                    'category_label' => $disease->category->label(),
                    'detection' => $disease->detection,
                    'icd_prefixes' => $disease->icd_prefixes,
                    'case_definition' => $disease->case_definition,
                    'notification_hours' => $disease->notification_hours,
                    'requires_contact_tracing' => $disease->requires_contact_tracing,
                    'is_active' => $disease->is_active,
                    'cases_count' => $disease->cases_count,
                ]),
        ]);
    }

    /**
     * Switch detection for a disease on or off.
     */
    public function update(Request $request, NotifiableDisease $disease): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $disease->update(['is_active' => $data['is_active']]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $disease->is_active
                ? "Detection switched on for {$disease->name}."
                : "Detection switched off for {$disease->name}.",
        ]);

        return back();
    }
}
