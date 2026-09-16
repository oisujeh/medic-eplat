<?php

namespace App\Http\Controllers;

use App\Enums\FormEntryMode;
use App\Enums\FormFieldType;
use App\Http\Requests\SaveFormSubmissionRequest;
use App\Http\Requests\VoidFormSubmissionRequest;
use App\Http\Resources\PatientBannerResource;
use App\Models\Encounter;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\LabTest;
use App\Services\FormSubmissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Filling a clinical form on an encounter: open, save as you go, complete,
 * void.
 */
class FormSubmissionController extends Controller
{
    public function __construct(private readonly FormSubmissions $submissions) {}

    /**
     * Start the form on the encounter, or resume the one already started.
     */
    public function open(Request $request, Encounter $encounter, Form $form): RedirectResponse
    {
        abort_unless($request->user()->can('document', $encounter), 403);
        abort_unless($form->status->isOffered() && $form->canBeFilledBy($request->user()), 403);
        abort_unless(in_array($encounter->type->value, $form->encounter_types, true), 404);

        $submission = $this->submissions->open($form, $encounter, $request->user());

        return redirect()->route('forms.show', $submission);
    }

    /**
     * The form to fill, rendered with the version it was started under.
     */
    public function show(Request $request, FormSubmission $submission): Response
    {
        abort_unless($request->user()->can('view', $submission), 403);

        // The form is loaded whole: the policy reads its roles and status.
        $submission->load([
            'form',
            'version',
            'patient',
            'encounter.servicePoint:id,name',
            'filledBy:id,name',
            'completedBy:id,name',
            'voidedBy:id,name',
        ]);

        $definition = $submission->version->parsed();
        $usesLabTests = collect($definition->fields())->contains(fn ($f) => ($f['type'] ?? null) === FormFieldType::LabTest->value);

        return Inertia::render('forms/Fill', [
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status->value,
                'status_label' => $submission->status->label(),
                'entry_mode' => $submission->entry_mode->value,
                'data' => $submission->data ?? [],
                'version' => $submission->version->version,
                'started_at_label' => $submission->started_at->isoFormat('D MMM YYYY, h:mm a'),
                'last_saved_at' => $submission->last_saved_at?->toIso8601String(),
                'filled_by' => $submission->filledBy?->name,
                'completed_at_label' => $submission->completed_at?->isoFormat('D MMM YYYY, h:mm a'),
                'completed_by' => $submission->completedBy?->name,
                'voided_at_label' => $submission->voided_at?->isoFormat('D MMM YYYY, h:mm a'),
                'voided_by' => $submission->voidedBy?->name,
                'void_reason' => $submission->void_reason,
                'urls' => [
                    'save' => route('forms.save', $submission),
                    'complete' => route('forms.complete', $submission),
                    'void' => route('forms.void', $submission),
                    'encounter' => $submission->encounter ? route('encounters.show', $submission->encounter) : null,
                ],
            ],
            'form' => [
                'id' => $submission->form->id,
                'slug' => $submission->form->slug,
                'name' => $submission->form->name,
                'description' => $submission->form->description,
            ],
            'definition' => $definition->toArray(),
            'context' => $this->submissions->context($submission),
            'patient' => PatientBannerResource::make($submission->patient),
            'encounter' => $submission->encounter ? [
                'id' => $submission->encounter->id,
                'type_label' => $submission->encounter->type->label(),
                'service_point' => $submission->encounter->servicePoint?->name,
                'is_open' => $submission->encounter->isOpen(),
                'date_label' => $submission->encounter->clinicalDate()?->isoFormat('D MMM YYYY'),
            ] : null,
            'entryModes' => FormEntryMode::options(),
            'labTests' => $usesLabTests
                ? LabTest::active()->orderBy('name')->get(['id', 'code', 'name'])
                    ->map(fn (LabTest $t) => ['id' => $t->id, 'code' => $t->code, 'name' => $t->name])->values()
                : [],
            'can' => [
                'fill' => $request->user()->can('fill', $submission),
                'void' => $request->user()->can('void', $submission),
            ],
        ]);
    }

    /**
     * Save the answers so far. Called as the user types, so it answers in
     * JSON when asked for it.
     */
    public function save(SaveFormSubmissionRequest $request, FormSubmission $submission): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->can('fill', $submission), 403);

        $submission = $this->submissions->save($submission, $request->answers(), $request->user(), $request->entryMode());

        if ($request->wantsJson()) {
            return response()->json([
                'saved_at' => $submission->last_saved_at?->toIso8601String(),
                'saved_at_label' => $submission->last_saved_at?->isoFormat('h:mm:ss a'),
                'data' => $submission->data,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved.']);

        return back();
    }

    public function complete(SaveFormSubmissionRequest $request, FormSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->can('fill', $submission), 403);

        $submission = $this->submissions->complete($submission, $request->answers(), $request->user(), $request->entryMode());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$submission->form->name} completed."]);

        return $submission->encounter
            ? redirect()->route('encounters.show', $submission->encounter)
            : back();
    }

    public function void(VoidFormSubmissionRequest $request, FormSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->can('void', $submission), 403);

        $this->submissions->void($submission, $request->validated('reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$submission->form->name} voided."]);

        return $submission->encounter
            ? redirect()->route('encounters.show', $submission->encounter)
            : back();
    }
}
