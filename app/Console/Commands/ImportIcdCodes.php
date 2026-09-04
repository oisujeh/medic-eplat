<?php

namespace App\Console\Commands;

use App\Models\IcdCode;
use Illuminate\Console\Command;

class ImportIcdCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'icd:import
        {path : A CSV or semicolon-separated file with code, description and optional chapter columns}
        {--deactivate-missing : Deactivate catalogue codes not present in the file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load ICD-10 codes into the diagnosis catalogue from a CSV file (e.g. the WHO release)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $delimiter = $this->detectDelimiter($path);
        $seen = [];
        $imported = 0;
        $skipped = 0;

        while (($row = $this->readRow($handle, $delimiter)) !== false) {
            $code = trim((string) ($row[0] ?? ''));
            $description = trim((string) ($row[1] ?? ''));

            if ($code === '' || $description === '' || ! preg_match('/^[A-Za-z]\d{2}(\.?\d{1,2})?$/', str_replace(' ', '', $code))) {
                $skipped++;

                continue;
            }

            $code = IcdCode::normalise($code);

            IcdCode::query()->updateOrCreate(
                ['code' => $code],
                [
                    'description' => $description,
                    'chapter' => trim((string) ($row[2] ?? '')) ?: null,
                    'is_active' => true,
                ],
            );

            $seen[] = $code;
            $imported++;
        }

        fclose($handle);

        if ($this->option('deactivate-missing') && $seen !== []) {
            $deactivated = IcdCode::query()->whereNotIn('code', $seen)->update(['is_active' => false]);
            $this->line("Deactivated {$deactivated} codes not in the file.");
        }

        $this->info("Imported {$imported} codes, skipped {$skipped} rows.");

        return self::SUCCESS;
    }

    /**
     * Read the next [code, description, chapter] row. Delimited files come
     * from the WHO and from spreadsheets; the CMS ICD-10-CM release lists a
     * code, whitespace, then the description.
     *
     * @param  resource  $handle
     * @return array<int, string>|false
     */
    private function readRow($handle, string $delimiter): array|false
    {
        if ($delimiter !== ' ') {
            return fgetcsv($handle, 0, $delimiter, '"', '\\');
        }

        $line = fgets($handle);

        if ($line === false) {
            return false;
        }

        $parts = preg_split('/\s+/', trim($line), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Files from the WHO and from spreadsheets differ in delimiter, and the
     * CMS release uses none at all.
     */
    private function detectDelimiter(string $path): string
    {
        $firstLine = (string) fgets(fopen($path, 'r'));

        // A code padded with spaces or a tab before its description is the
        // CMS layout, whatever punctuation the description contains.
        if (preg_match('/^\s*[A-Za-z]\d{2}\.?\d{0,2}(\s{2,}|\t)\S/', $firstLine)) {
            return ' ';
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }
}
