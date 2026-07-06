<?php

namespace App\Actions\Billing;

use App\Models\Charge;
use App\Models\Matter;

class AddCharge
{
    public function handle(Matter $matter, array $data): Charge
    {
        return $matter->charges()->create([
            'type' => $data['type'],
            'date' => $data['date'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'currency_code' => $matter->billingCurrency(),
        ]);
    }
}
