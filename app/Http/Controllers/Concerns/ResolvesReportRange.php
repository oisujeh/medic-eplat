<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared date-range resolution for the reporting screens: turns a preset key
 * (or explicit from/to pair) into a bounded Carbon range.
 */
trait ResolvesReportRange
{
    /** Day-count presets. */
    private array $rangePresets = ['7d' => 7, '30d' => 30, '90d' => 90];

    /**
     * Resolve the requested range, defaulting to the last 30 days.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function resolveRange(Request $request): array
    {
        $range = (string) $request->query('range', '30d');
        $today = Carbon::today();

        if ($range === 'custom' && $request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->query('from'));
            $to = Carbon::parse($request->query('to'));

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

        // The month most recently completed: what an NHMIS return covers.
        if ($range === 'last_month') {
            $start = $today->copy()->subMonthNoOverflow()->startOfMonth();

            return [$start, $start->copy()->endOfMonth()->startOfDay(), 'last_month'];
        }

        if ($range === 'ytd') {
            return [$today->copy()->startOfYear(), $today, 'ytd'];
        }

        $key = array_key_exists($range, $this->rangePresets) ? $range : '30d';

        return [$today->copy()->subDays($this->rangePresets[$key] - 1), $today, $key];
    }

    /**
     * A human-readable label for the resolved range.
     */
    protected function rangeLabel(Carbon $from, Carbon $to): string
    {
        return $from->isoFormat('D MMM YYYY').' – '.$to->isoFormat('D MMM YYYY');
    }

    /**
     * The selectable range presets for the toolbar.
     *
     * @return array<int, array{key: string, label: string}>
     */
    protected function rangePresetOptions(): array
    {
        return [
            ['key' => '7d', 'label' => 'Last 7 days'],
            ['key' => '30d', 'label' => 'Last 30 days'],
            ['key' => '90d', 'label' => 'Last 90 days'],
            ['key' => 'mtd', 'label' => 'This month'],
            ['key' => 'last_month', 'label' => 'Last month'],
            ['key' => 'ytd', 'label' => 'This year'],
        ];
    }
}
