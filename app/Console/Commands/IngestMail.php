<?php

namespace App\Console\Commands;

use App\Services\Mailroom\IngestInboundMail;
use Illuminate\Console\Command;

class IngestMail extends Command
{
    protected $signature = 'mail:ingest';

    protected $description = 'Ingest inbound emails from the mailbox drop into the mailroom';

    public function handle(IngestInboundMail $ingest): int
    {
        $stats = $ingest->ingestFromInbox();

        $this->info("Ingested {$stats['ingested']} email(s), {$stats['matched']} matched to matters.");

        return self::SUCCESS;
    }
}
