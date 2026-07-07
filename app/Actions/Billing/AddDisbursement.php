<?php

namespace App\Actions\Billing;

use App\Models\Disbursement;
use App\Models\Matter;
use App\Services\ExchangeRateService;
use Illuminate\Support\Carbon;

class AddDisbursement
{
    public function __construct(private ExchangeRateService $fx) {}

    public function handle(Matter $matter, array $data): Disbursement
    {
        $currency = $matter->billingCurrency();
        $markup = (float) ($data['markup_pct']
            ?? $matter->effectiveBillingAgreement()?->default_markup_pct
            ?? 0);

        // Billed amount: cost plus markup, converted to the billing currency.
        $amount = $this->fx->convert(
            (float) $data['cost_amount'] * (1 + $markup / 100),
            $data['cost_currency'],
            $currency,
            Carbon::parse($data['date'])
        );

        return $matter->disbursements()->create([
            'date' => $data['date'],
            'description' => $data['description'],
            'supplier' => $data['supplier'] ?? null,
            'cost_amount' => $data['cost_amount'],
            'cost_currency' => $data['cost_currency'],
            'markup_pct' => $markup,
            'amount' => $amount,
            'base_amount' => $this->fx->toBase($amount, $currency, Carbon::parse($data['date'])),
            'currency_code' => $currency,
        ]);
    }
}
