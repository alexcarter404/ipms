<?php

namespace App\Console\Commands;

use App\Services\Integrations\IngestOfficeMessages;
use Illuminate\Console\Command;

class PollIpoOffices extends Command
{
    protected $signature = 'ipo:poll';

    protected $description = 'Poll IP office connectors and ingest inbound messages';

    public function handle(IngestOfficeMessages $ingest): int
    {
        $stats = $ingest->pollAll();

        $this->info(sprintf(
            'Ingested %d message(s): %d auto-processed, %d awaiting review.',
            $stats['ingested'], $stats['processed'], $stats['review']
        ));

        return self::SUCCESS;
    }
}
