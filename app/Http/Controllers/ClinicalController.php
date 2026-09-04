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
 * The clinician console: the consultation worklist and the door into an
 * encounter.
 */
class ClinicalController extends Controller
{
    use PresentsWorklist;

    /** The module that governs clinical service points. */
    private const MODULE = 'clinical';

    public function __construct(private readonly EncounterService $encounters) {}

    /**
     * Patients waiting at clinical service points, plus the clinician's
     * recently signed consultations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('clinical/Index', [
            'queue' => $this->worklistFor(self::MODULE, $user, 'clinical.consult'),
            'seesAll' => $user->hasFullModuleAccess(),
            'recent' => $this->recentEncountersFor($user, EncounterType::Consultation),
        ]);
    }

    /**
     * Open (or resume) the consultation for a queue entry. Opening claims the
     * patient, then hands over to the encounter screen.
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
