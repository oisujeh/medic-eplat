<?php

namespace App\Http\Controllers;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Http\Requests\UpdateDataQualityRuleRequest;
use App\Models\DataQualityRule;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rule registry: what each check looks for, how serious a failure is,
 * whose queue it lands in and the thresholds it reads.
 */
class DataQualityRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $rules = DataQualityRule::query()
            ->withCount(['issues as open_issues_count' => fn ($query) => $query->where('status', 'open')])
            ->orderBy('dimension')
            ->orderBy('sort_order')
            ->get()
            ->map(function (DataQualityRule $rule): array {
                $check = $rule->check();

                return [
                    'id' => $rule->id,
                    'key' => $rule->key,
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'dimension' => $rule->dimension->value,
                    'dimension_label' => $rule->dimension->label(),
                    'severity' => $rule->severity->value,
                    'owner_role' => $rule->owner_role,
                    'parameters' => $rule->effectiveParameters(),
                    'parameter_fields' => $check === null ? [] : $check::parameterFields(),
                    'runs_on_write' => $check !== null && $check::runsOnWrite(),
                    'is_active' => $rule->is_active,
                    'open_issues_count' => $rule->open_issues_count,
                    'version' => $rule->currentVersion()->version,
                    'missing_check' => $check === null,
                ];
            });

        return Inertia::render('data-quality/Rules', [
            'rules' => $rules,
            'dimensions' => DataQualityDimension::options(),
            'severities' => DataQualitySeverity::options(),
            'roles' => Role::query()
                ->where('grants_all_modules', false)
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (Role $role) => ['value' => $role->slug, 'label' => $role->name]),
            'can' => [
                'manage_rules' => $request->user()->can('data-quality.manage_rules'),
            ],
        ]);
    }

    /**
     * Retune a rule. Changing what it means opens a new version; switching it
     * on or off does not.
     */
    public function update(UpdateDataQualityRuleRequest $request, DataQualityRule $rule): RedirectResponse
    {
        $data = $request->validated();
        $parameters = $rule->effectiveParameters();

        foreach ($data['parameters'] ?? [] as $key => $value) {
            if (array_key_exists($key, $parameters)) {
                $parameters[$key] = is_numeric($value) ? (int) $value : $value;
            }
        }

        $rule->update([
            'severity' => $data['severity'],
            'owner_role' => $data['owner_role'] ?? null,
            'parameters' => $parameters,
            'is_active' => $data['is_active'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $rule->is_active
                ? "{$rule->name} updated."
                : "{$rule->name} switched off; its open issues stay in the queue until they are resolved or waived.",
        ]);

        return back();
    }
}
