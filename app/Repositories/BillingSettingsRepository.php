<?php

namespace App\Repositories;

use App\Models\ActivityCode;
use App\Models\ExchangeRate;
use App\Models\RateCard;
use App\Models\TaxRate;
use Illuminate\Support\Collection;

/**
 * Billing reference data: tax rates, exchange rates, activity codes,
 * rate cards.
 */
class BillingSettingsRepository
{
    public function taxRates(): Collection
    {
        return TaxRate::orderByDesc('is_default')->orderBy('name')->get();
    }

    public function taxRateOptions(): array
    {
        return $this->taxRates()
            ->map(fn (TaxRate $rate) => [
                'value' => $rate->id,
                'label' => sprintf('%s (%s%%)', $rate->name, rtrim(rtrim((string) $rate->rate, '0'), '.')),
            ])
            ->all();
    }

    /** The latest stored rate per currency. */
    public function latestExchangeRates(): Collection
    {
        return ExchangeRate::orderByDesc('rate_date')
            ->get()
            ->unique('currency_code')
            ->sortBy('currency_code')
            ->values();
    }

    public function activityCodes(): Collection
    {
        return ActivityCode::orderBy('code')->get();
    }

    public function activityCodeOptions(): array
    {
        return $this->activityCodes()
            ->map(fn (ActivityCode $code) => [
                'value' => $code->id,
                'label' => "{$code->code} — {$code->description}",
            ])
            ->all();
    }

    public function rateCards(): Collection
    {
        return RateCard::with(['user:id,name', 'client:id,name', 'activityCode:id,code'])
            ->get()
            ->sortByDesc(fn (RateCard $card) => [$card->specificity(), $card->effective_from->timestamp])
            ->values();
    }
}
