<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Selectable range presets, in days back from today. */
    private const PRESETS = ['7d' => 7, '30d' => 30, '90d' => 90];

    public function __construct(
        private readonly ReportService $reports,
    ) {}

    /**
     * The executive reporting dashboard for a selected date range.
     */
    public function index(Request $request): Response
    {
        [$from, $to, $range] = $this->resolveRange($request);

        return Inertia::render('reports/Overview', [
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $this->rangeLabel($from, $to),
            ],
            'presets' => [
                ['key' => '7d', 'label' => 'Last 7 days'],
                ['key' => '30d', 'label' => 'Last 30 days'],
                ['key' => '90d', 'label' => 'Last 90 days'],
                ['key' => 'mtd', 'label' => 'This month'],
                ['key' => 'ytd', 'label' => 'This year'],
            ],
            'report' => $this->reports->overview($from, $to),
            'generatedAt' => now()->isoFormat('D MMM YYYY, h:mm a'),
        ]);
    }

    /**
     * Resolve the requested date range from a preset key or an explicit
     * from/to pair, falling back to the last 30 days.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $range = (string) $request->query('range', '30d');
        $today = Carbon::today();

        if ($range === 'custom' && $request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->query('from'));
            $to = Carbon::parse($request->query('to'));

            // Guard against an inverted range and cap the span at one year.
            if ($from->greaterThan($to)) {
                [$from, $to] = [$to, $from];
            }
            if ($from->diffInDays($to) > 366) {
                $from = $to->copy()->subYear();
            }

            return [$from, $to, 'custom'];
        }

        if ($range === 'mtd') {
            return [$today->copy()->startOfMonth(), $today, 'mtd'];
        }

        if ($range === 'ytd') {
            return [$today->copy()->startOfYear(), $today, 'ytd'];
        }

        $key = array_key_exists($range, self::PRESETS) ? $range : '30d';
        $days = self::PRESETS[$key];

        return [$today->copy()->subDays($days - 1), $today, $key];
    }

    /**
     * A human-readable label for the resolved range.
     */
    private function rangeLabel(Carbon $from, Carbon $to): string
    {
        return $from->isoFormat('D MMM YYYY').' – '.$to->isoFormat('D MMM YYYY');
    }
}
