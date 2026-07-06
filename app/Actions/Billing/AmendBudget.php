<?php

namespace App\Actions\Billing;

use App\Models\Budget;
use App\Services\ExchangeRateService;

/**
 * Amend a budget row. The creator and creation time are preserved for
 * the audit trail; the base value is re-frozen at the amendment date.
 */
class AmendBudget
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    public function handle(Budget $budget, array $data): Budget
    {
        $currency = $data['currency_code'] ?? $budget->currency_code;

        $budget->update([
            'description' => $data['description'] ?? $budget->description,
            'amount' => $data['amount'],
            'currency_code' => $currency,
            'base_amount' => $this->fx->toBase((float) $data['amount'], $currency),
        ]);

        return $budget;
    }
}
