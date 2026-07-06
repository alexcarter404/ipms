<?php

namespace App\Http\Controllers;

use App\Actions\Billing\SaveBillingAgreement;
use App\Http\Requests\BillingAgreementRequest;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;

class BillingAgreementController extends Controller
{
    public function save(BillingAgreementRequest $request, Matter $matter, SaveBillingAgreement $action): RedirectResponse
    {
        $action->handle($matter, $request->validated());

        return back()->with('success', 'Billing agreement saved.');
    }
}
