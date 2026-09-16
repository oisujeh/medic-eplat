<?php

use App\Enums\FormSubmissionStatus;
use App\Enums\StaffCadre;
use App\Enums\TimelineKind;
use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormValue;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\FormCatalogue;
use App\Services\PatientTimeline;
use Database\Seeders\FormsSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(FormsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function fillingUser(array $roleSlugs, ?StaffCadre $cadre = null): User
{
    $user = User::factory()->create(['cadre' => $cadre]);
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * A consultation opened by a physician, with the operation note offered.
 *
 * @return array{0: Encounter, 1: User}
 */
function fillingConsultation(?User $physician = null): array
{
    $patient = Patient::factory()->create(['date_of_birth' => now()->subYears(40)]);
    actingAs(fillingUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);
    $entry = QueueEntry::latest('id')->firstOrFail();
    $physician ??= fillingUser(['physician'], StaffCadre::Doctor);

    return [openEncounter($entry, $physician), $physician];
}

/**
 * A complete set of answers for the seeded operation note.
 *
 * @return array<string, mixed>
 */
function operationNoteAnswers(): array
{
    return [
        'procedure' => 'Open appendicectomy',
        'urgency' => 'emergency',
        'anaesthesia' => 'general',
        'surgeon' => 'Dr Bello',
        'start' => now()->subHours(3)->format('Y-m-d\TH:i'),
        'end' => now()->subHours(2)->subMinutes(15)->format('Y-m-d\TH:i'),
        'findings' => 'Inflamed, non-perforated appendix.',
        'ebl' => '150',
        'transfused' => false,
        'complication' => true,
        'complication_detail' => 'Minor serosal tear, repaired.',
        'specimens' => [
            ['specimen_type' => 'Appendix', 'specimen_site' => 'RIF', 'histology' => true],
            ['specimen_type' => 'Peritoneal fluid', 'specimen_site' => '', 'histology' => false],
        ],
        'drain' => false,
        'postop' => 'IV fluids, analgesia, review in 6 hours.',
    ];
}

test('the encounter offers its forms and opening one starts a draft at the current version', function () {
    [$encounter, $physician] = fillingConsultation();
    $form = Form::where('slug', 'operation-note')->firstOrFail();

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('forms.available', 1)
            ->where('forms.available.0.name', 'Operation note')
            ->has('forms.submissions', 0));

    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]))->assertRedirect();

    $submission = FormSubmission::firstOrFail();

    expect($submission->status)->toBe(FormSubmissionStatus::Draft)
        ->and($submission->form_version_id)->toBe($form->currentVersion->id)
        ->and($submission->patient_id)->toBe($encounter->patient_id)
        ->and($submission->encounter_id)->toBe($encounter->id)
        ->and($submission->filled_by)->toBe($physician->id)
        // Defaults are applied and repeating groups start empty.
        ->and($submission->data)->toMatchArray(['transfused' => false, 'complication' => false, 'specimens' => []]);

    // Opening again resumes the same draft rather than starting another.
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]))->assertRedirect(route('forms.show', $submission));
    expect(FormSubmission::count())->toBe(1);

    actingAs($physician)
        ->get(route('forms.show', $submission))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/Fill')
            ->where('form.slug', 'operation-note')
            ->where('submission.version', 1)
            ->where('context.age', 40)
            ->where('can.fill', true)
            ->where('can.void', true)
            ->has('definition.pages', 2));

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertInertia(fn ($page) => $page
            ->has('forms.available', 0)
            ->where('forms.submissions.0.status', 'draft'));
});

test('typing is saved as a draft with calculations refreshed and nothing required yet', function () {
    [$encounter, $physician] = fillingConsultation();
    $form = Form::where('slug', 'operation-note')->firstOrFail();
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]));
    $submission = FormSubmission::firstOrFail();

    actingAs($physician)
        ->patchJson(route('forms.save', $submission), [
            'data' => ['procedure' => 'Herniorrhaphy', 'start' => '2026-09-10T09:00', 'end' => '2026-09-10T10:30'],
            'entry_mode' => 'rapid',
        ])
        ->assertOk()
        ->assertJsonPath('data.duration', 90)
        ->assertJsonStructure(['saved_at', 'saved_at_label']);

    $submission->refresh();

    expect($submission->status)->toBe(FormSubmissionStatus::Draft)
        ->and($submission->entry_mode->value)->toBe('rapid')
        ->and($submission->last_saved_at)->not->toBeNull()
        ->and($submission->data['procedure'])->toBe('Herniorrhaphy')
        ->and($submission->data['duration'])->toBe(90)
        ->and(FormValue::count())->toBe(0);
});

test('completing checks every visible answer and flattens the rest into the value table', function () {
    [$encounter, $physician] = fillingConsultation();
    $form = Form::where('slug', 'operation-note')->firstOrFail();
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]));
    $submission = FormSubmission::firstOrFail();

    $bad = operationNoteAnswers();
    $bad['surgeon'] = '';
    $bad['end'] = $bad['start'];
    $bad['ebl'] = 9999;
    $bad['transfused'] = true;
    $bad['specimens'][1]['specimen_type'] = '';

    actingAs($physician)
        ->post(route('forms.complete', $submission), ['data' => $bad])
        ->assertSessionHasErrors([
            'data.surgeon', 'data.end', 'data.ebl', 'data.transfused_units', 'data.specimens.1.specimen_type',
        ]);

    expect($submission->fresh()->status)->toBe(FormSubmissionStatus::Draft);

    actingAs($physician)
        ->post(route('forms.complete', $submission), ['data' => operationNoteAnswers()])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('encounters.show', $encounter));

    $submission->refresh();

    expect($submission->status)->toBe(FormSubmissionStatus::Completed)
        ->and($submission->completed_by)->toBe($physician->id)
        ->and($submission->data['duration'])->toBe(45);

    $values = FormValue::where('form_submission_id', $submission->id)->get();

    expect($values->pluck('concept_id')->filter()->count())->toBe($values->count())
        ->and($values->firstWhere('field_key', 'duration'))->toMatchArray(['value_type' => 'numeric', 'value_numeric' => 45.0, 'unit' => 'min'])
        ->and($values->firstWhere('field_key', 'anaesthesia'))->toMatchArray(['value_type' => 'coded', 'value_reference' => 'general', 'value_text' => 'General'])
        ->and($values->firstWhere('field_key', 'anaesthesia')->value_concept_id)->not->toBeNull()
        ->and($values->firstWhere('field_key', 'complication')->value_boolean)->toBeTrue()
        ->and($values->firstWhere('field_key', 'complication_detail')->value_text)->toBe('Minor serosal tear, repaired.')
        ->and($values->where('field_key', 'specimen_type')->pluck('value_text')->all())->toBe(['Appendix', 'Peritoneal fluid'])
        ->and($values->where('group_key', 'specimens')->pluck('repeat_index')->unique()->values()->all())->toBe([0, 1])
        ->and($values->where('field_key', 'transfused_units')->count())->toBe(0)
        ->and($values->every(fn (FormValue $v) => $v->patient_id === $encounter->patient_id && $v->encounter_id === $encounter->id))->toBeTrue();

    // Completing again after an edit rewrites the rows rather than adding.
    $again = operationNoteAnswers();
    $again['specimens'] = [];

    actingAs($physician)->post(route('forms.complete', $submission), ['data' => $again])->assertSessionHasNoErrors();

    expect(FormValue::where('form_submission_id', $submission->id)->where('group_key', 'specimens')->count())->toBe(0)
        ->and(FormValue::where('form_submission_id', $submission->id)->where('field_key', 'procedure')->count())->toBe(1);
});

test('a submission keeps rendering with the version it was filled under', function () {
    [$encounter, $physician] = fillingConsultation();
    $form = Form::where('slug', 'operation-note')->firstOrFail();
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]));
    $submission = FormSubmission::firstOrFail();

    $catalogue = app(FormCatalogue::class);
    $definition = $form->definition;
    $definition['pages'][0]['sections'][0]['fields'][0]['label'] = 'Procedure (revised)';
    $catalogue->updateDraft($form, ['definition' => $definition]);
    $catalogue->publish($form, fillingUser(['super-administrator']));

    expect($form->fresh()->published_version)->toBe(2);

    actingAs($physician)
        ->get(route('forms.show', $submission))
        ->assertInertia(fn ($page) => $page
            ->where('submission.version', 1)
            ->where('definition.pages.0.sections.0.fields.0.label', 'Procedure performed'));

    // A form started on another encounter now gets version 2.
    [$later, $other] = fillingConsultation();
    actingAs($other)->get(route('encounters.forms.open', [$later, $form]));

    expect(FormSubmission::latest('id')->first()->version->version)->toBe(2);
});

test('voiding needs the permission and a reason, and empties the value table', function () {
    [$encounter, $physician] = fillingConsultation();
    $form = Form::where('slug', 'operation-note')->firstOrFail();
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]));
    $submission = FormSubmission::firstOrFail();
    actingAs($physician)->post(route('forms.complete', $submission), ['data' => operationNoteAnswers()]);

    expect(FormValue::count())->toBeGreaterThan(0);

    actingAs($physician)->post(route('forms.void', $submission), ['reason' => 'no'])->assertSessionHasErrors(['reason']);

    actingAs($physician)
        ->post(route('forms.void', $submission), ['reason' => 'Entered on the wrong patient.'])
        ->assertSessionHasNoErrors();

    $submission->refresh();

    expect($submission->status)->toBe(FormSubmissionStatus::Voided)
        ->and($submission->void_reason)->toBe('Entered on the wrong patient.')
        ->and($submission->voided_by)->toBe($physician->id)
        ->and($submission->data['procedure'])->toBe('Open appendicectomy')
        ->and(FormValue::count())->toBe(0)
        ->and(AuditLog::where('auditable_type', FormSubmission::class)->where('reason', 'Entered on the wrong patient.')->exists())->toBeTrue();

    // A voided form is closed; the form can be started afresh.
    actingAs($physician)->patchJson(route('forms.save', $submission), ['data' => []])->assertForbidden();
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]));

    expect(FormSubmission::count())->toBe(2);
});

test('forms are offered by encounter type and role', function () {
    [$encounter, $physician] = fillingConsultation();
    $form = Form::where('slug', 'operation-note')->firstOrFail();

    // A nurse on a nursing encounter: wrong type and not a named role.
    $nurse = fillingUser(['nurse'], StaffCadre::Nurse);
    $nursing = Encounter::factory()->nursing('triage')->create();

    actingAs($nurse)
        ->get(route('encounters.show', $nursing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('forms.available', 0));

    actingAs($nurse)->get(route('encounters.forms.open', [$nursing, $form]))->assertForbidden();

    // The physician's consultation offers it; a second physician who does
    // not hold the patient cannot open it.
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]))->assertRedirect();
    $submission = FormSubmission::firstOrFail();

    $bystander = fillingUser(['physician'], StaffCadre::Doctor);
    actingAs($bystander)->get(route('forms.show', $submission))->assertForbidden();

    // Once the encounter is signed, the form is readable but locked.
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Post-op']);

    actingAs($physician)
        ->get(route('forms.show', $submission))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.fill', false));

    actingAs($physician)->patchJson(route('forms.save', $submission), ['data' => []])->assertForbidden();
});

test('a completed form shows on the patient chart timeline and under its encounter', function () {
    [$encounter, $physician] = fillingConsultation();
    $form = Form::where('slug', 'operation-note')->firstOrFail();
    actingAs($physician)->get(route('encounters.forms.open', [$encounter, $form]));
    $submission = FormSubmission::firstOrFail();

    // A draft is on the timeline, flagged as such.
    $draft = app(PatientTimeline::class)->entries($encounter->patient)->firstWhere('kind', TimelineKind::Form);

    expect($draft)->not->toBeNull()
        ->and($draft->badge)->toBe('Draft')
        ->and($draft->href)->toBe(route('forms.show', $submission));

    actingAs($physician)->post(route('forms.complete', $submission), ['data' => operationNoteAnswers()])->assertSessionHasNoErrors();
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Appendicitis'])->assertSessionHasNoErrors();

    actingAs($physician)
        ->get(route('patients.show', $encounter->patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('encounters.0.forms.0.form_name', 'Operation note')
            ->where('encounters.0.forms.0.status', 'completed')
            ->where('encounters.0.forms.0.by', $physician->name)
            ->where('timeline.entries', fn ($entries) => collect($entries)->contains(fn ($e) => $e['kind'] === 'form' && $e['title'] === 'Operation note' && $e['badge'] === null)));

    // A voided form leaves the history.
    actingAs($physician)->post(route('forms.void', $submission), ['reason' => 'Entered on the wrong patient'])->assertSessionHasNoErrors();

    expect(app(PatientTimeline::class)->entries($encounter->patient->fresh())->where('kind', TimelineKind::Form))->toHaveCount(0);

    actingAs($physician)
        ->get(route('patients.show', $encounter->patient))
        ->assertInertia(fn ($page) => $page->has('encounters.0.forms', 0));
});
