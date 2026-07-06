<?php

namespace App\Console\Commands;

use App\Exceptions\DomainActionException;
use App\Services\ExchangeRateSync;
use Illuminate\Console\Command;

class SyncExchangeRates extends Command
{
    protected $signature = 'billing:sync-rates';

    protected $description = 'Fetch the latest exchange rates from the configured provider';

    public function handle(ExchangeRateSync $sync): int
    {
        try {
            $result = $sync->sync();
        } catch (DomainActionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Synced %d rates for %s (base %s).',
            count($result['rates']),
            $result['date'],
            config('billing.base_currency')
        ));

        return self::SUCCESS;
    }
}
