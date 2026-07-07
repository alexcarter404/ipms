<?php

namespace App\Services\Invoicing;

use App\Enums\BillableStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\DomainActionException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\InvoiceRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InternalInvoicingProvider implements InvoicingProvider
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainActionException('Only draft invoices can be issued.');
        }

        $invoice->update([
            'invoice_no' => $this->invoices->nextNumber(),
            'status' => InvoiceStatus::Issued,
            'issued_at' => Carbon::today(),
            'due_at' => Carbon::today()->addDays(config('billing.payment_terms_days')),
        ]);

        return $invoice;
    }

    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Paid], true)) {
            throw new DomainActionException('Payments can only be recorded against issued invoices.');
        }

        $payment = $invoice->payments()->create($data);

        if ($invoice->balance() <= 0) {
            $invoice->update(['status' => InvoiceStatus::Paid]);
        }

        return $payment;
    }

    public function void(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Issued) {
            throw new DomainActionException('Only issued, unpaid invoices can be voided.');
        }

        return DB::transaction(function () use ($invoice) {
            $this->releaseBillables($invoice);
            $invoice->update(['status' => InvoiceStatus::Void]);

            return $invoice;
        });
    }

    /** Free the WIP items on this invoice so they can be rebilled. */
    public static function releaseBillables(Invoice $invoice): void
    {
        foreach ($invoice->lines()->with('billable')->get() as $line) {
            $line->billable?->update([
                'status' => BillableStatus::Billable,
                'invoice_line_id' => null,
            ]);
        }
    }
}
