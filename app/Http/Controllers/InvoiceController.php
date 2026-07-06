<?php

namespace App\Http\Controllers;

use App\Actions\Billing\DeleteDraftInvoice;
use App\Actions\Billing\DraftInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\DomainActionException;
use App\Http\Requests\DraftInvoiceRequest;
use App\Models\Invoice;
use App\Models\Matter;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceRepository $invoices)
    {
    }

    public function index(Request $request, ClientRepository $clients): Response
    {
        $filters = $request->only('status', 'client_id');

        return Inertia::render('Billing/Invoices/Index', [
            'invoices' => $this->invoices->paginateFiltered($filters),
            'filters' => $filters,
            'statuses' => InvoiceStatus::options(),
            'clients' => $clients->options(),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $this->invoices->loadForDisplay($invoice);

        return Inertia::render('Billing/Invoices/Show', [
            'invoice' => array_merge($invoice->toArray(), [
                'display_number' => $invoice->displayNumber(),
                'amount_paid' => $invoice->amountPaid(),
                'balance' => $invoice->balance(),
            ]),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function store(DraftInvoiceRequest $request, Matter $matter, DraftInvoice $action): RedirectResponse
    {
        try {
            $invoice = $action->handle($matter, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', sprintf(
                'Draft invoice created — %d line(s), %s %s.',
                $invoice->lines->count(),
                $invoice->currency_code,
                number_format((float) $invoice->total, 2)
            ));
    }

    public function issue(Invoice $invoice, IssueInvoice $action): RedirectResponse
    {
        try {
            $action->handle($invoice);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Invoice issued as {$invoice->invoice_no}.");
    }

    public function void(Invoice $invoice, VoidInvoice $action): RedirectResponse
    {
        try {
            $action->handle($invoice);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice voided — its items are billable again.');
    }

    public function destroy(Invoice $invoice, DeleteDraftInvoice $action): RedirectResponse
    {
        try {
            $action->handle($invoice);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.index')
            ->with('success', 'Draft invoice deleted — its items are billable again.');
    }
}
