<?php

namespace App\Actions\Billing;

use App\Models\Budget;
use App\Models\Matter;
use App\Models\User;
use App\Services\ExchangeRateService;

class AddBudget
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    public function handle(Matter $matter, User $creator, array $data): Budget
    {
        $currency = $data['currency_code'] ?? $matter->billingCurrency();

        return $matter->budgets()->create([
            'created_by' => $creator->id,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'currency_code' => $currency,
            'base_amount' => $this->fx->toBase((float) $data['amount'], $currency),
        ]);
    }
}
