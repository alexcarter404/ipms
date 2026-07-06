<?php

namespace App\Http\Controllers;

use App\Actions\Billing\AddDisbursement;
use App\Actions\Billing\AmendBillable;
use App\Actions\Billing\DeleteBillable;
use App\Actions\Billing\UpdateBillableStatus;
use App\Enums\BillableStatus;
use App\Exceptions\DomainActionException;
use App\Http\Requests\DisbursementRequest;
use App\Models\Disbursement;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    public function store(DisbursementRequest $request, Matter $matter, AddDisbursement $action): RedirectResponse
    {
        try {
            $disbursement = $action->handle($matter, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Disbursement added — billed at %s %s.',
            $disbursement->currency_code,
            number_format((float) $disbursement->amount, 2)
        ));
    }

    public function update(Request $request, Disbursement $disbursement, AmendBillable $action): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
        ]);

        try {
            $action->handle($disbursement, $data);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Disbursement updated.');
    }

    public function updateStatus(Request $request, Disbursement $disbursement, UpdateBillableStatus $action): RedirectResponse
    {
        $request->validate(['status' => ['required', 'string']]);

        try {
            $action->handle($disbursement, BillableStatus::from($request->string('status')->value()));
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Disbursement updated.');
    }

    public function destroy(Disbursement $disbursement, DeleteBillable $action): RedirectResponse
    {
        try {
            $action->handle($disbursement);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Disbursement deleted.');
    }
}
