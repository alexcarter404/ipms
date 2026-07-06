<?php

namespace App\Actions\Billing;

use App\Enums\ChargeType;
use App\Exceptions\DomainActionException;
use App\Models\BillingAgreementStage;
use App\Models\Charge;
use Illuminate\Support\Carbon;

/**
 * A stage-payment milestone has been reached: raise its charge so it
 * appears in WIP ready to invoice.
 */
class RaiseStageCharge
{
    public function handle(BillingAgreementStage $stage): Charge
    {
        if ($stage->charge()->exists()) {
            throw new DomainActionException('This stage has already been charged.');
        }

        $matter = $stage->agreement->matter;

        return $matter->charges()->create([
            'stage_id' => $stage->id,
            'type' => ChargeType::StagePayment,
            'date' => Carbon::today(),
            'description' => $stage->description,
            'amount' => $stage->amount,
            'currency_code' => $matter->billingCurrency(),
        ]);
    }
}
