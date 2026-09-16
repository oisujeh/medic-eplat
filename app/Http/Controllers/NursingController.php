<?php

namespace App\Http\Controllers;

use App\Enums\EncounterType;
use App\Http\Controllers\Concerns\PresentsWorklist;
use App\Models\QueueEntry;
use App\Services\EncounterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The nursing console: the worklist for triage, antenatal care, family
 * planning and immunization, and the door into a nursing encounter.
 */
class NursingController extends Controller
{
    use PresentsWorklist;

    /** The module that governs nursing service points. */
    private const MODULE = 'nursing';

    public function __construct(private readonly EncounterService $encounters) {}

    /**
     * Patients waiting at nursing service points, plus the nurse's recently
     * signed encounters.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('nursing/Index', [
            'queue' => $this->worklistFor(self::MODULE, $user, 'nursing.workspace'),
            'seesAll' => $user->hasFullModuleAccess(),
            'recent' => $this->recentEncountersFor($user, EncounterType::Triage, EncounterType::Nursing),
        ]);
    }

    /**
     * Open (or resume) the nursing encounter for a queue entry. Opening claims
     * the patient, then hands over to the encounter screen.
     */
    public function open(Request $request, QueueEntry $entry): RedirectResponse
    {
        $entry->loadMissing('servicePoint');
        abort_unless($entry->servicePoint->module_slug === self::MODULE, 404);

        $encounter = $this->encounters->openForQueueEntry($entry, $request->user());
        abort_unless($request->user()->can('view', $encounter), 403);

        return to_route('encounters.show', $encounter);
    }
}
