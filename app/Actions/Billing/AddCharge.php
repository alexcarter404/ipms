<?php

namespace App\Actions\Billing;

use App\Models\Charge;
use App\Models\Matter;
use App\Services\ExchangeRateService;
use Illuminate\Support\Carbon;

class AddCharge
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    public function handle(Matter $matter, array $data): Charge
    {
        $currency = $matter->billingCurrency();

        return $matter->charges()->create([
            'type' => $data['type'],
            'date' => $data['date'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'base_amount' => $this->fx->toBase((float) $data['amount'], $currency, Carbon::parse($data['date'])),
            'currency_code' => $currency,
        ]);
    }
}
