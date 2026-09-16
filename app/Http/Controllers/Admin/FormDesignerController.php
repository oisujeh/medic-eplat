<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EncounterType;
use App\Enums\FormEntryMode;
use App\Enums\FormFieldType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportFormRequest;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Role;
use App\Services\FormCatalogue;
use App\Support\Forms\Calculations;
use App\Support\Forms\Conditions;
use App\Support\Forms\FormDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The form designer for the health-informatics team: the catalogue of
 * forms, the working draft of each, and the publish, retire, import and
 * export actions on it.
 */
class FormDesignerController extends Controller
{
    public function __construct(private readonly FormCatalogue $catalogue) {}

    public function index(Request $request): Response
    {
        $forms = Form::query()
            ->withCount(['submissions' => fn ($q) => $q->live()])
            ->with('currentVersion')
            ->orderBy('name')
            ->get()
            ->map(fn (Form $form) => [
                'id' => $form->id,
                'slug' => $form->slug,
                'name' => $form->name,
                'description' => $form->description,
                'status' => $form->status->value,
                'status_label' => $form->status->label(),
                'entry_mode_label' => $form->entry_mode->label(),
                'encounter_types' => collect($form->encounter_types)
                    ->map(fn (string $t) => EncounterType::tryFrom($t)?->label() ?? $t)
                    ->values(),
                'roles' => $form->roles,
                'published_version' => $form->published_version,
                'has_unpublished_changes' => $form->hasUnpublishedChanges(),
                'submissions_count' => $form->submissions_count,
                'updated_at_label' => $form->updated_at?->isoFormat('D MMM YYYY'),
                'url' => route('admin.forms.show', $form),
            ]);

        return Inertia::render('admin/forms/Index', [
            'forms' => $forms,
            'options' => $this->options(),
            'canManage' => $request->user()->hasPermission(Permission::ManageForms),
        ]);
    }

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $form = $this->catalogue->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$form->name} created as a draft."]);

        return redirect()->route('admin.forms.show', $form);
    }

    public function show(Request $request, Form $form): Response
    {
        $form->load('currentVersion');

        $versions = $form->versions()
            ->withCount(['submissions' => fn ($q) => $q->live()])
            ->with('publishedBy:id,name')
            ->get()
            ->map(fn (FormVersion $v) => [
                'version' => $v->version,
                'published_at_label' => $v->published_at->isoFormat('D MMM YYYY, h:mm a'),
                'published_by' => $v->publishedBy?->name,
                'notes' => $v->notes,
                'submissions_count' => $v->submissions_count,
            ]);

        return Inertia::render('admin/forms/Designer', [
            'form' => [
                'id' => $form->id,
                'slug' => $form->slug,
                'name' => $form->name,
                'description' => $form->description,
                'status' => $form->status->value,
                'status_label' => $form->status->label(),
                'entry_mode' => $form->entry_mode->value,
                'encounter_types' => $form->encounter_types,
                'roles' => $form->roles,
                'definition' => $form->definition,
                'published_version' => $form->published_version,
                'has_unpublished_changes' => $form->hasUnpublishedChanges(),
                'urls' => [
                    'update' => route('admin.forms.update', $form),
                    'publish' => route('admin.forms.publish', $form),
                    'retire' => route('admin.forms.retire', $form),
                    'restore' => route('admin.forms.restore', $form),
                    'export' => route('admin.forms.export', $form),
                    'index' => route('admin.forms.index'),
                ],
            ],
            'issues' => $this->catalogue->issues($form),
            'versions' => $versions,
            'options' => $this->options(),
            // A stand-in patient for the live preview (FRM-10: test with a
            // sample patient).
            'sampleContext' => [
                'age' => 32,
                'age_days' => 32 * 365,
                'sex' => 'F',
                'encounter_at' => now()->toIso8601String(),
                'today' => now()->toDateString(),
            ],
            'canManage' => $request->user()->hasPermission(Permission::ManageForms),
        ]);
    }

    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
        $this->catalogue->updateDraft($form, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved.']);

        return back();
    }

    public function publish(Request $request, Form $form): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $version = $this->catalogue->publish($form, $request->user(), $data['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$form->name} published as version {$version->version}."]);

        return back();
    }

    public function retire(Form $form): RedirectResponse
    {
        $this->catalogue->retire($form);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$form->name} retired. Filled forms are unaffected."]);

        return back();
    }

    public function restore(Form $form): RedirectResponse
    {
        $this->catalogue->restore($form);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$form->name} is offered again."]);

        return back();
    }

    /**
     * The form as a file to carry to another installation.
     */
    public function export(Form $form): JsonResponse
    {
        return response()
            ->json($this->catalogue->export($form), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', 'attachment; filename="'.Str::slug($form->slug).'.form.json"');
    }

    public function import(ImportFormRequest $request): RedirectResponse
    {
        $form = $this->catalogue->import($request->payload(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$form->name} imported as a draft. Review it, then publish."]);

        return redirect()->route('admin.forms.show', $form);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'encounterTypes' => collect(EncounterType::cases())
                ->map(fn (EncounterType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
            'roles' => Role::query()->where('grants_all_modules', false)->orderBy('name')->get(['slug', 'name'])
                ->map(fn (Role $r) => ['value' => $r->slug, 'label' => $r->name])
                ->values(),
            'entryModes' => FormEntryMode::options(),
            'fieldTypes' => FormFieldType::options(),
            'conditionOperators' => Conditions::OPERATORS,
            'calculationOperators' => Calculations::OPERATORS,
            'contextKeys' => FormDefinition::CONTEXT_KEYS,
        ];
    }
}
