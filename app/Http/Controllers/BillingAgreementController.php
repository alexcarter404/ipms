<?php

namespace App\Http\Controllers;

use App\Actions\Billing\SaveBillingAgreement;
use App\Exceptions\DomainActionException;
use App\Http\Requests\BillingAgreementRequest;
use App\Models\ClientEntity;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;

class BillingAgreementController extends Controller
{
    public function save(BillingAgreementRequest $request, Matter $matter, SaveBillingAgreement $action): RedirectResponse
    {
        $action->handle($matter, $request->validated());

        return back()->with('success', 'Billing agreement saved.');
    }

    /** The entity-level default handed down to the entity's matters. */
    public function saveForEntity(BillingAgreementRequest $request, ClientEntity $entity, SaveBillingAgreement $action): RedirectResponse
    {
        try {
            $action->forEntity($entity, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Default fee agreement saved for {$entity->name}.");
    }

    /** Drop a matter's override so it falls back to the entity default. */
    public function destroy(Matter $matter): RedirectResponse
    {
        $matter->billingAgreement?->delete();

        return back()->with('success', 'Override removed — the entity default applies.');
    }
}
