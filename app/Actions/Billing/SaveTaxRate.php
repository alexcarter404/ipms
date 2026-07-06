<?php

namespace App\Actions\Billing;

use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class SaveTaxRate
{
    public function handle(array $data, ?TaxRate $taxRate = null): TaxRate
    {
        return DB::transaction(function () use ($data, $taxRate) {
            $taxRate = $taxRate
                ? tap($taxRate)->update($data)
                : TaxRate::create($data);

            // At most one default rate.
            if ($taxRate->is_default) {
                TaxRate::whereKeyNot($taxRate->id)->update(['is_default' => false]);
            }

            return $taxRate;
        });
    }
}
