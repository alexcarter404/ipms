<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\Payment;

/**
 * The last-mile invoicing seam. The internal provider keeps the whole
 * lifecycle in the IPMS; an external driver (Xero, QuickBooks, Stripe)
 * can implement this to push invoices out and sync status back without
 * touching the WIP layer.
 */
interface InvoicingProvider
{
    /** Finalise a draft: assign a number, set dates, mark issued. */
    public function issue(Invoice $invoice): Invoice;

    /** Record money received; mark the invoice paid when settled. */
    public function recordPayment(Invoice $invoice, array $data): Payment;

    /** Cancel an issued invoice. */
    public function void(Invoice $invoice): Invoice;
}
