<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Invoicing\InvoicingProvider;

class RecordPayment
{
    public function __construct(private InvoicingProvider $provider) {}

    public function handle(Invoice $invoice, array $data): Payment
    {
        return $this->provider->recordPayment($invoice, $data);
    }
}
