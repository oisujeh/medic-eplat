<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesReportRange;
use App\Services\DataQualityMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How the register as a whole is doing: the pass rate of every rule, the four
 * dimensions side by side, and whether issues are being closed faster than
 * they are raised.
 */
class DataQualityScorecardController extends Controller
{
    use ResolvesReportRange;

    public function __construct(private readonly DataQualityMetrics $metrics) {}

    public function show(Request $request): Response
    {
        [$from, $to, $range] = $this->resolveRange($request);

        $scorecard = $this->metrics->scorecard($from->copy()->startOfDay(), $to->copy()->endOfDay());

        return Inertia::render('data-quality/Scorecard', [
            ...$scorecard,
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $this->rangeLabel($from, $to),
            ],
            'presets' => $this->rangePresetOptions(),
            'can' => [
                'manage_rules' => $request->user()->can('data-quality.manage_rules'),
            ],
        ]);
    }
}
