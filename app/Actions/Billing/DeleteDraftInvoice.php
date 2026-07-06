<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Exceptions\DomainActionException;
use App\Models\Invoice;
use App\Services\Invoicing\InternalInvoicingProvider;
use Illuminate\Support\Facades\DB;

class DeleteDraftInvoice
{
    public function handle(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainActionException('Only draft invoices can be deleted — void issued invoices instead.');
        }

        DB::transaction(function () use ($invoice) {
            InternalInvoicingProvider::releaseBillables($invoice);
            $invoice->delete();
        });
    }
}
