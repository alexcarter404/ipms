<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Matter;
use App\Services\InvoiceBuilder;

class DraftInvoice
{
    public function __construct(private InvoiceBuilder $builder)
    {
    }

    public function handle(Matter $matter, array $options = []): Invoice
    {
        return $this->builder->draft($matter, $options);
    }
}
