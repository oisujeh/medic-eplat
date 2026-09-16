<?php

namespace App\Console\Commands;

use App\Services\AuditTrail;
use Illuminate\Console\Command;

class VerifyAuditTrail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute the audit trail hash chain and report the first entry that has been tampered with';

    /**
     * Execute the console command.
     */
    public function handle(AuditTrail $audit): int
    {
        $result = $audit->verify();

        if ($result['intact']) {
            $this->info("Audit trail intact: {$result['checked']} entries verified.");

            return self::SUCCESS;
        }

        $this->error("Audit trail broken at entry #{$result['broken_id']} after {$result['checked']} intact entries. That entry, or one before it, was altered or removed outside the application.");

        return self::FAILURE;
    }
}
