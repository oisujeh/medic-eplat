<?php

namespace App\Http\Controllers;

use App\Enums\LabDepartment;
use App\Enums\Permission;
use App\Http\Requests\StoreLabReferenceRangeRequest;
use App\Models\LabReferenceRange;
use App\Models\LabTest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The test catalogue and its reference ranges by age band and sex.
 */
class LabCatalogueController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $tests = LabTest::query()
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->withCount('referenceRanges')
            ->orderBy('department')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (LabTest $test) => [
                'id' => $test->id,
                'code' => $test->code,
                'name' => $test->name,
                'department' => $test->department->value,
                'department_label' => $test->department->label(),
                'unit' => $test->unit,
                'is_panel' => $test->is_panel,
                'reference' => $test->referenceLabel(),
                'bands' => $test->reference_ranges_count,
                'url' => route('laboratory.catalogue.show', $test),
            ]);

        return Inertia::render('laboratory/Catalogue', [
            'tests' => $tests,
            'filters' => ['q' => $search],
            'departments' => collect(LabDepartment::cases())->map(fn (LabDepartment $d) => [
                'value' => $d->value,
                'label' => $d->label(),
            ])->values(),
            'canManage' => $request->user()->hasPermission(Permission::ManageLabCatalogue),
        ]);
    }

    public function show(Request $request, LabTest $test): Response
    {
        $test->load('referenceRanges');

        return Inertia::render('laboratory/Test', [
            'test' => [
                'id' => $test->id,
                'code' => $test->code,
                'name' => $test->name,
                'department_label' => $test->department->label(),
                'unit' => $test->unit,
                'specimen_type' => $test->specimen_type,
                'is_panel' => $test->is_panel,
                'reference' => $test->referenceLabel(),
                'reference_low' => $test->reference_low,
                'reference_high' => $test->reference_high,
                'reference_text' => $test->reference_text,
            ],
            'ranges' => $test->referenceRanges
                ->sortBy(fn (LabReferenceRange $r) => [$r->sex ?? 'Z', $r->age_min_days])
                ->values()
                ->map(fn (LabReferenceRange $r) => [
                    'id' => $r->id,
                    'sex' => $r->sex,
                    'sex_label' => match ($r->sex) {
                        'F' => 'Female', 'M' => 'Male', default => 'Any'
                    },
                    'age_min_days' => $r->age_min_days,
                    'age_max_days' => $r->age_max_days,
                    'age_label' => $this->ageLabel($r->age_min_days, $r->age_max_days),
                    'low' => $r->low,
                    'high' => $r->high,
                    'critical_low' => $r->critical_low,
                    'critical_high' => $r->critical_high,
                    'text' => $r->text,
                ]),
            'canManage' => $request->user()->hasPermission(Permission::ManageLabCatalogue),
        ]);
    }

    public function storeRange(StoreLabReferenceRangeRequest $request, LabTest $test): RedirectResponse
    {
        $test->referenceRanges()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Reference band added to {$test->name}."]);

        return back();
    }

    public function destroyRange(LabTest $test, LabReferenceRange $range): RedirectResponse
    {
        abort_unless($range->lab_test_id === $test->id, 404);

        $range->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reference band removed.']);

        return back();
    }

    /**
     * An age span in the unit a scientist would say it in: days under a
     * month, months under two years, otherwise years.
     */
    private function ageLabel(int $minDays, ?int $maxDays): string
    {
        $format = function (int $days): string {
            if ($days < 30) {
                return $days.'d';
            }

            if ($days < 730) {
                return round($days / 30).'m';
            }

            return round($days / 365, 1) == round($days / 365) ? round($days / 365).'y' : round($days / 365, 1).'y';
        };

        if ($minDays === 0 && $maxDays === null) {
            return 'All ages';
        }

        if ($maxDays === null) {
            return $format($minDays).' and over';
        }

        return $format($minDays).' to '.$format($maxDays);
    }
}
