<?php

namespace App\Services;

use App\Enums\EncounterType;
use App\Enums\FormEntryMode;
use App\Enums\FormStatus;
use App\Models\ConceptSource;
use App\Models\Encounter;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Role;
use App\Models\User;
use App\Support\Forms\FormDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The forms catalogue: drafting, publishing and retiring form definitions,
 * moving them between installations as JSON, and answering which forms an
 * encounter offers.
 */
class FormCatalogue
{
    public const EXPORT_FORMAT = 'medic-eplat.form';

    public function __construct(private readonly Terminology $terminology) {}

    /**
     * Start a new form as a draft.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $actor = null): Form
    {
        return Form::create([
            'slug' => $this->uniqueSlug((string) ($attributes['slug'] ?? $attributes['name'])),
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'status' => FormStatus::Draft,
            'entry_mode' => $attributes['entry_mode'] ?? FormEntryMode::Guided->value,
            'encounter_types' => array_values($attributes['encounter_types'] ?? []),
            'roles' => array_values($attributes['roles'] ?? []),
            'definition' => $attributes['definition'] ?? FormDefinition::blank(),
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Change the working draft or where the form is offered. Publishing is
     * what makes the change reach clinicians.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDraft(Form $form, array $attributes): Form
    {
        $form->fill(collect($attributes)
            ->only(['name', 'description', 'entry_mode', 'encounter_types', 'roles', 'definition'])
            ->map(fn ($value, $key) => in_array($key, ['encounter_types', 'roles'], true) ? array_values((array) $value) : $value)
            ->all());
        $form->save();

        return $form;
    }

    /**
     * What stops the working draft being published: structural problems,
     * concept codes the dictionary does not know, and a form offered
     * nowhere.
     *
     * @return list<string>
     */
    public function issues(Form $form): array
    {
        $definition = $form->draftDefinition();
        $issues = $definition->issues();

        foreach ($definition->conceptCodes() as $code) {
            if ($this->conceptId($code) === null) {
                $issues[] = "Concept code \"{$code}\" is not in the dictionary.";
            }
        }

        if ($form->encounter_types === []) {
            $issues[] = 'Assign the form to at least one encounter type before publishing.';
        }

        return $issues;
    }

    /**
     * Freeze the working draft as the next version and offer it to
     * clinicians. Concept codes are resolved to ids at this moment, so a
     * published version never depends on a code being looked up later.
     *
     * @throws ValidationException when the draft cannot be published
     */
    public function publish(Form $form, ?User $actor = null, ?string $notes = null): FormVersion
    {
        $issues = $this->issues($form);

        if ($issues !== []) {
            throw ValidationException::withMessages(['definition' => $issues]);
        }

        $resolved = $form->draftDefinition()->resolveConcepts(fn (string $code) => $this->conceptId($code));

        return DB::transaction(function () use ($form, $actor, $notes, $resolved) {
            $number = ($form->published_version ?? 0) + 1;

            $version = $form->versions()->create([
                'version' => $number,
                'definition' => $resolved->toArray(),
                'notes' => $notes,
                'published_by' => $actor?->id,
                'published_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]);

            $form->update([
                'definition' => $resolved->toArray(),
                'status' => FormStatus::Published,
                'published_version' => $number,
            ]);

            return $version;
        });
    }

    /**
     * Withdraw the form from clinicians. What was filled stays as it is.
     */
    public function retire(Form $form): Form
    {
        $form->update(['status' => FormStatus::Retired]);

        return $form;
    }

    /**
     * Offer a retired form again, at its last published version.
     *
     * @throws ValidationException when the form was never published
     */
    public function restore(Form $form): Form
    {
        if ($form->published_version === null) {
            throw ValidationException::withMessages(['status' => 'The form has never been published; publish it instead.']);
        }

        $form->update(['status' => FormStatus::Published]);

        return $form;
    }

    /**
     * The form as a portable file: concepts by code, not by id.
     *
     * @return array<string, mixed>
     */
    public function export(Form $form): array
    {
        return [
            'format' => self::EXPORT_FORMAT,
            'format_version' => 1,
            'slug' => $form->slug,
            'name' => $form->name,
            'description' => $form->description,
            'entry_mode' => $form->entry_mode->value,
            'encounter_types' => $form->encounter_types,
            'roles' => $form->roles,
            'published_version' => $form->published_version,
            'exported_at' => Carbon::now()->toIso8601String(),
            'definition' => $form->draftDefinition()->withoutConceptIds()->toArray(),
        ];
    }

    /**
     * Load a form file into the catalogue as a draft: a new form, or the
     * next draft of the form with the same slug. Nothing is published.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException when the file is not a form
     */
    public function import(array $payload, ?User $actor = null): Form
    {
        if (($payload['format'] ?? null) !== self::EXPORT_FORMAT || ! is_array($payload['definition'] ?? null) || ! is_string($payload['name'] ?? null)) {
            throw ValidationException::withMessages(['file' => 'This is not a form file: it needs "format", "name" and "definition".']);
        }

        $attributes = [
            'name' => $payload['name'],
            'description' => is_string($payload['description'] ?? null) ? $payload['description'] : null,
            'entry_mode' => (FormEntryMode::tryFrom((string) ($payload['entry_mode'] ?? '')) ?? FormEntryMode::Guided)->value,
            'encounter_types' => array_values(array_filter(
                (array) ($payload['encounter_types'] ?? []),
                fn ($t) => is_string($t) && EncounterType::tryFrom($t) !== null,
            )),
            'roles' => array_values(array_filter(
                (array) ($payload['roles'] ?? []),
                fn ($r) => is_string($r) && Role::where('slug', $r)->exists(),
            )),
            'definition' => $payload['definition'],
        ];

        $slug = is_string($payload['slug'] ?? null) ? Str::slug($payload['slug']) : Str::slug($payload['name']);
        $existing = Form::where('slug', $slug)->first();

        if ($existing !== null) {
            return $this->updateDraft($existing, $attributes);
        }

        return $this->create([...$attributes, 'slug' => $slug], $actor);
    }

    /**
     * The published forms an encounter offers the member of staff working
     * it (PRD FRM-11): assigned to its encounter type, and to a role they
     * hold when the form names roles.
     *
     * @return Collection<int, Form>
     */
    public function availableFor(Encounter $encounter, User $user): Collection
    {
        return Form::query()
            ->published()
            ->forEncounterType($encounter->type)
            ->orderBy('name')
            ->get()
            ->filter(fn (Form $form) => $form->canBeFilledBy($user))
            ->values();
    }

    private function conceptId(string $code): ?int
    {
        return $this->terminology->resolve(ConceptSource::LOCAL, $code)?->id;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'form';
        $candidate = $slug;

        for ($n = 2; Form::where('slug', $candidate)->exists(); $n++) {
            $candidate = "{$slug}-{$n}";
        }

        return $candidate;
    }
}
