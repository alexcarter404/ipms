<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Services\Invoicing\InvoicingProvider;

class IssueInvoice
{
    public function __construct(private InvoicingProvider $provider)
    {
    }

    public function handle(Invoice $invoice): Invoice
    {
        return $this->provider->issue($invoice);
    }
}
