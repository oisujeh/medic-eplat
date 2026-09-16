<?php

use App\Enums\ConceptClass;
use App\Enums\FormStatus;
use App\Models\ConceptSource;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\FormCatalogue;
use App\Services\Terminology;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    ConceptSource::firstOrCreate(['code' => ConceptSource::LOCAL], ['name' => 'Facility local codes']);

    $terminology = app(Terminology::class);
    $terminology->register(ConceptClass::Observation, 'T-WEIGHT', 'Weight', [], [], ['datatype' => 'numeric', 'unit' => 'kg']);
    $terminology->register(ConceptClass::Observation, 'T-NOTE', 'Note', [], [], ['datatype' => 'text']);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function designerUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * @return array<string, mixed>
 */
function designerDefinition(): array
{
    return ['pages' => [[
        'key' => 'p1', 'title' => 'Page', 'sections' => [[
            'key' => 's1', 'title' => 'Section', 'fields' => [
                ['key' => 'weight', 'type' => 'numeric', 'label' => 'Weight', 'concept' => 'T-WEIGHT', 'unit' => 'kg', 'required' => true],
                ['key' => 'note', 'type' => 'text', 'label' => 'Note', 'concept' => 'T-NOTE'],
            ],
        ]],
    ]]];
}

test('an administrator drafts, publishes and versions a form', function () {
    $admin = designerUser(['super-administrator']);

    actingAs($admin)
        ->post(route('admin.forms.store'), ['name' => 'Nutrition assessment', 'encounter_types' => ['consultation']])
        ->assertRedirect();

    $form = Form::where('slug', 'nutrition-assessment')->firstOrFail();

    expect($form->status)->toBe(FormStatus::Draft)
        ->and($form->created_by)->toBe($admin->id)
        ->and($form->definition['pages'])->toHaveCount(1);

    // The blank draft has no fields, so it is not publishable yet.
    actingAs($admin)
        ->get(route('admin.forms.show', $form))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/forms/Designer')
            ->where('form.slug', 'nutrition-assessment')
            ->where('canManage', true)
            ->where('issues', []));

    actingAs($admin)
        ->patch(route('admin.forms.update', $form), ['definition' => designerDefinition(), 'roles' => ['physician', 'nurse']])
        ->assertSessionHasNoErrors();

    actingAs($admin)
        ->post(route('admin.forms.publish', $form), ['notes' => 'First release'])
        ->assertSessionHasNoErrors();

    $form->refresh();

    expect($form->status)->toBe(FormStatus::Published)
        ->and($form->published_version)->toBe(1)
        ->and($form->roles)->toBe(['physician', 'nurse'])
        ->and($form->currentVersion->notes)->toBe('First release')
        ->and($form->currentVersion->definition['pages'][0]['sections'][0]['fields'][0]['concept_id'])->not->toBeNull()
        ->and($form->hasUnpublishedChanges())->toBeFalse();

    // Editing the draft leaves version 1 as it was; publishing again makes 2.
    $definition = designerDefinition();
    $definition['pages'][0]['sections'][0]['fields'][0]['max'] = 300;

    actingAs($admin)->patch(route('admin.forms.update', $form), ['definition' => $definition]);

    expect($form->fresh()->hasUnpublishedChanges())->toBeTrue()
        ->and(FormVersion::where('form_id', $form->id)->where('version', 1)->first()->definition['pages'][0]['sections'][0]['fields'][0])->not->toHaveKey('max');

    actingAs($admin)->post(route('admin.forms.publish', $form))->assertSessionHasNoErrors();

    expect($form->fresh()->published_version)->toBe(2)
        ->and(FormVersion::where('form_id', $form->id)->count())->toBe(2);
});

test('publishing refuses a definition with problems or unknown concepts', function () {
    $admin = designerUser(['super-administrator']);
    $catalogue = app(FormCatalogue::class);

    $form = $catalogue->create(['name' => 'Broken', 'encounter_types' => ['consultation']], $admin);

    $definition = designerDefinition();
    $definition['pages'][0]['sections'][0]['fields'][1]['concept'] = 'T-NOPE';
    $definition['pages'][0]['sections'][0]['fields'][] = ['key' => 'weight', 'type' => 'text', 'label' => 'Dup', 'concept' => 'T-NOTE'];

    $catalogue->updateDraft($form, ['definition' => $definition]);

    actingAs($admin)->post(route('admin.forms.publish', $form))->assertSessionHasErrors(['definition']);

    expect($form->fresh()->status)->toBe(FormStatus::Draft)
        ->and(implode("\n", $catalogue->issues($form)))
        ->toContain('Concept code "T-NOPE" is not in the dictionary')
        ->toContain('used more than once');

    // A form offered to no encounter type cannot be published either.
    $unassigned = $catalogue->create(['name' => 'Nowhere', 'definition' => designerDefinition()], $admin);

    expect($catalogue->issues($unassigned))->toContain('Assign the form to at least one encounter type before publishing.');
});

test('a form is retired and restored without touching its versions', function () {
    $admin = designerUser(['super-administrator']);
    $catalogue = app(FormCatalogue::class);
    $form = $catalogue->create(['name' => 'Ward round', 'encounter_types' => ['ward_round'], 'definition' => designerDefinition()], $admin);
    $catalogue->publish($form, $admin);

    actingAs($admin)->post(route('admin.forms.retire', $form))->assertSessionHasNoErrors();
    expect($form->fresh()->status)->toBe(FormStatus::Retired);

    actingAs($admin)->post(route('admin.forms.restore', $form))->assertSessionHasNoErrors();
    expect($form->fresh()->status)->toBe(FormStatus::Published)
        ->and($form->fresh()->published_version)->toBe(1);

    expect(fn () => $form->currentVersion->update(['notes' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $form->currentVersion->delete())->toThrow(LogicException::class);
});

test('a form travels as JSON by concept code and comes back as a draft', function () {
    $admin = designerUser(['super-administrator']);
    $catalogue = app(FormCatalogue::class);
    $form = $catalogue->create(['name' => 'Travel', 'slug' => 'travel', 'encounter_types' => ['consultation'], 'roles' => ['nurse'], 'definition' => designerDefinition()], $admin);
    $catalogue->publish($form, $admin);

    $response = actingAs($admin)->get(route('admin.forms.export', $form))->assertOk();
    $file = $response->json();

    expect($file['format'])->toBe('medic-eplat.form')
        ->and($file['definition']['pages'][0]['sections'][0]['fields'][0])->not->toHaveKey('concept_id')
        ->and($file['definition']['pages'][0]['sections'][0]['fields'][0]['concept'])->toBe('T-WEIGHT');

    // Importing under the same slug replaces the draft, not the versions.
    $file['name'] = 'Travel (revised)';
    $file['description'] = 'Brought back';

    actingAs($admin)
        ->post(route('admin.forms.import'), ['json' => json_encode($file)])
        ->assertRedirect(route('admin.forms.show', $form));

    $form->refresh();

    expect($form->name)->toBe('Travel (revised)')
        ->and($form->description)->toBe('Brought back')
        ->and($form->published_version)->toBe(1)
        ->and($form->hasUnpublishedChanges())->toBeTrue();

    // A new slug makes a new form.
    $file['slug'] = 'travel-copy';

    actingAs($admin)->post(route('admin.forms.import'), ['json' => json_encode($file)]);

    expect(Form::where('slug', 'travel-copy')->exists())->toBeTrue()
        ->and(Form::where('slug', 'travel-copy')->first()->status)->toBe(FormStatus::Draft);

    actingAs($admin)
        ->post(route('admin.forms.import'), ['json' => '{"hello": 1}'])
        ->assertSessionHasErrors(['file']);
});

test('only the administration module and the forms permission reach the designer', function () {
    $physician = designerUser(['physician']);
    $form = app(FormCatalogue::class)->create(['name' => 'Locked', 'definition' => designerDefinition()]);

    actingAs($physician)->get(route('admin.forms.index'))->assertForbidden();
    actingAs($physician)->patch(route('admin.forms.update', $form), ['name' => 'Renamed'])->assertForbidden();

    expect($form->fresh()->name)->toBe('Locked');

    actingAs(designerUser(['super-administrator']))
        ->get(route('admin.forms.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/forms/Index')
            ->where('forms.0.name', 'Locked')
            ->where('forms.0.status', 'draft'));
});
