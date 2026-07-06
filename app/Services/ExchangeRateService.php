<?php

namespace App\Services;

use App\Exceptions\DomainActionException;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;

/**
 * Converts between billing currencies using stored daily rates
 * (1 base unit = rate × currency). The rate on or nearest before the
 * given date wins; the latest known rate is the fallback.
 */
class ExchangeRateService
{
    /** Units of $currency per 1 unit of the base currency. */
    public function rate(string $currency, ?CarbonInterface $date = null): float
    {
        if ($currency === config('billing.base_currency')) {
            return 1.0;
        }

        $rate = ExchangeRate::where('currency_code', $currency)
            ->when($date, fn ($q) => $q->where('rate_date', '<=', $date->toDateString()))
            ->orderByDesc('rate_date')
            ->value('rate')
            // No rate on/before the date — fall back to the earliest known
            ?? ExchangeRate::where('currency_code', $currency)
                ->orderBy('rate_date')
                ->value('rate');

        if ($rate === null) {
            throw new DomainActionException(
                "No exchange rate available for {$currency}. Add one in Billing Settings or run billing:sync-rates."
            );
        }

        return (float) $rate;
    }

    public function convert(float $amount, string $from, string $to, ?CarbonInterface $date = null): float
    {
        if ($from === $to) {
            return round($amount, 2);
        }

        return round($amount / $this->rate($from, $date) * $this->rate($to, $date), 2);
    }
}
