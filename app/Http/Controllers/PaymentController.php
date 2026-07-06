<?php

namespace App\Http\Controllers;

use App\Actions\Billing\RecordPayment;
use App\Exceptions\DomainActionException;
use App\Http\Requests\PaymentRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Invoice $invoice, RecordPayment $action): RedirectResponse
    {
        try {
            $action->handle($invoice, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        $invoice->refresh();

        return back()->with('success', $invoice->status->value === 'paid'
            ? 'Payment recorded — invoice settled in full.'
            : sprintf('Payment recorded — %s %s outstanding.', $invoice->currency_code, number_format($invoice->balance(), 2)));
    }
}
