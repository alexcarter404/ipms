<?php

namespace App\Http\Controllers;

use App\Actions\Billing\AddCharge;
use App\Actions\Billing\DeleteBillable;
use App\Actions\Billing\RaiseStageCharge;
use App\Exceptions\DomainActionException;
use App\Http\Requests\ChargeRequest;
use App\Models\BillingAgreementStage;
use App\Models\Charge;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;

class ChargeController extends Controller
{
    public function store(ChargeRequest $request, Matter $matter, AddCharge $action): RedirectResponse
    {
        $action->handle($matter, $request->validated());

        return back()->with('success', 'Charge added.');
    }

    public function raiseStage(BillingAgreementStage $stage, RaiseStageCharge $action): RedirectResponse
    {
        try {
            $charge = $action->handle($stage);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Stage payment raised: “{$charge->description}”.");
    }

    public function destroy(Charge $charge, DeleteBillable $action): RedirectResponse
    {
        try {
            $action->handle($charge);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Charge deleted.');
    }
}
