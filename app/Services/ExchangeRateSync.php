<?php

namespace App\Services;

use App\Exceptions\DomainActionException;
use App\Models\ExchangeRate;
use App\Support\Currencies;
use Illuminate\Support\Facades\Http;

/**
 * Pulls the day's rates from the configured provider (ECB-backed, keyless
 * by default) and upserts them against the base currency. Rates can also
 * be maintained by hand in Billing Settings.
 */
class ExchangeRateSync
{
    /** @return array{date: string, rates: array<string, float>} */
    public function sync(): array
    {
        $base = Currencies::base();
        $symbols = array_values(array_diff(Currencies::codes(), [$base]));

        $response = Http::timeout(15)->get(config('billing.rates_url'), [
            'base' => $base,
            'symbols' => implode(',', $symbols),
        ]);

        if ($response->failed() || ! is_array($response->json('rates'))) {
            throw new DomainActionException(
                'Could not fetch exchange rates from the provider. Rates can be entered manually in Billing Settings.'
            );
        }

        $date = $response->json('date') ?? now()->toDateString();
        $rates = [];

        foreach ($response->json('rates') as $currency => $rate) {
            ExchangeRate::updateOrCreate(
                ['currency_code' => $currency, 'rate_date' => $date],
                ['rate' => $rate]
            );
            $rates[$currency] = (float) $rate;
        }

        return ['date' => $date, 'rates' => $rates];
    }
}
