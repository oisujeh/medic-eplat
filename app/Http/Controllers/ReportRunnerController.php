<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesReportRange;
use App\Models\AuditLog;
use App\Services\AuditTrail;
use App\Services\ReportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportRunnerController extends Controller
{
    use ResolvesReportRange;

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * Run a report and render its results as a table.
     */
    public function show(Request $request, string $report): Response|RedirectResponse
    {
        $definition = $this->registry->find($report);
        abort_if($definition === null, 404);

        // Dashboard-type "reports" are their own screens.
        if ($definition['type'] === 'dashboard') {
            return redirect($definition['href'] ?? route('reports.index'));
        }

        [$from, $to, $range] = $this->resolveRange($request);
        $result = $this->registry->run($report, $from, $to);
        $rangeQuery = $this->rangeQuery($from, $to, $range);

        // Reports list patients in bulk, so running one is a record access.
        $this->audit->record(AuditLog::ACTION_VIEWED, label: $this->auditLabel($definition, $from, $to));

        return Inertia::render('reports/Report', [
            'report' => [
                'key' => $definition['key'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'category' => $this->registry->categories()[$definition['category']]['name'] ?? $definition['category'],
            ],
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $this->rangeLabel($from, $to),
            ],
            'presets' => $this->rangePresetOptions(),
            'exportUrl' => route('reports.export', ['report' => $report, ...$rangeQuery]),
        ]);
    }

    /**
     * Stream a report's rows as a CSV download.
     */
    public function export(Request $request, string $report): StreamedResponse
    {
        $definition = $this->registry->find($report);
        abort_if($definition === null || $definition['type'] === 'dashboard', 404);

        [$from, $to] = $this->resolveRange($request);
        $result = $this->registry->run($report, $from, $to);

        $filename = Str::slug($definition['name']).'_'.$from->toDateString().'_'.$to->toDateString().'.csv';

        $this->audit->record(AuditLog::ACTION_EXPORTED, label: $this->auditLabel($definition, $from, $to));

        return response()->streamDownload(function () use ($result) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel renders the ₦ sign correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(fn (array $c) => $c['label'], $result['columns']));

            $keys = array_map(fn (array $c) => $c['key'], $result['columns']);
            foreach ($result['rows'] as $row) {
                fputcsv($handle, array_map(fn (string $key) => $row[$key] ?? '', $keys));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * How a report run is described in the audit trail.
     *
     * @param  array<string, mixed>  $definition
     */
    private function auditLabel(array $definition, Carbon $from, Carbon $to): string
    {
        return "Report: {$definition['name']} ({$from->toDateString()} to {$to->toDateString()})";
    }

    /**
     * Build the range query-string parameters to carry through links.
     *
     * @return array<string, string>
     */
    private function rangeQuery(Carbon $from, Carbon $to, string $range): array
    {
        return $range === 'custom'
            ? ['range' => 'custom', 'from' => $from->toDateString(), 'to' => $to->toDateString()]
            : ['range' => $range];
    }
}
