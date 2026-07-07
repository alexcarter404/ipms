<?php

namespace App\Console\Commands;

use App\Services\Integrations\RegisterReconciliation;
use Illuminate\Console\Command;

class ReconcileRegisters extends Command
{
    protected $signature = 'ipo:reconcile';

    protected $description = 'Compare matters against the office registers and flag drift';

    public function handle(RegisterReconciliation $reconciliation): int
    {
        $stats = $reconciliation->run();

        $this->info("Checked {$stats['checked']} matter(s) — {$stats['drift']} drifted.");

        return self::SUCCESS;
    }
}
