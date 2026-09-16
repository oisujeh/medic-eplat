<?php

namespace App\Console\Commands;

use App\Services\DataQuality;
use Illuminate\Console\Command;

/**
 * The nightly data-quality pass: the rules no write can answer — work left
 * undone past its deadline — and the re-check that closes the issues somebody
 * has since put right.
 */
class SweepDataQuality extends Command
{
    protected $signature = 'data-quality:sweep';

    protected $description = 'Apply the data-quality rules to the whole register and close the issues that have been put right';

    public function handle(DataQuality $dataQuality): int
    {
        $started = microtime(true);

        $result = $dataQuality->sweep();

        $this->components->info(sprintf(
            '%d records checked, %d issues raised, %d closed in %.1fs.',
            $result['checked'],
            $result['raised'],
            $result['resolved'],
            microtime(true) - $started,
        ));

        return self::SUCCESS;
    }
}
