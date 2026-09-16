<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesReportRange;
use App\Services\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportCatalogController extends Controller
{
    use ResolvesReportRange;

    public function __construct(
        private readonly ReportRegistry $registry,
    ) {}

    /**
     * The report library: browse categories and launch any report.
     */
    public function index(Request $request): Response
    {
        [$from, $to, $range] = $this->resolveRange($request);
        $rangeQuery = $range === 'custom'
            ? ['range' => 'custom', 'from' => $from->toDateString(), 'to' => $to->toDateString()]
            : ['range' => $range];

        $reports = collect($this->registry->reports())
            ->map(fn (array $r) => [
                ...collect($r)->only(['key', 'name', 'category', 'description', 'icon', 'type', 'featured'])->all(),
                'url' => $r['type'] === 'dashboard'
                    ? ($r['href'] ?? '#')
                    : route('reports.run', ['report' => $r['key'], ...$rangeQuery]),
            ]);

        $countsByCategory = $reports->groupBy('category')->map->count();

        $categories = collect($this->registry->categories())
            ->map(fn (array $c, string $key) => [
                'key' => $key,
                'name' => $c['name'],
                'icon' => $c['icon'],
                'description' => $c['description'],
                'count' => $countsByCategory->get($key, 0),
            ])
            ->values();

        return Inertia::render('reports/Index', [
            'categories' => $categories,
            'reports' => $reports->values(),
            'featured' => $reports->where('featured', true)->values(),
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $this->rangeLabel($from, $to),
            ],
            'presets' => $this->rangePresetOptions(),
        ]);
    }
}
