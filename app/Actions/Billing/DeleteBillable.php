<?php

namespace App\Actions\Billing;

use App\Enums\BillableStatus;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;

class DeleteBillable
{
    public function handle(Model $billable): void
    {
        if ($billable->status === BillableStatus::Billed) {
            throw new DomainActionException('This item is on an invoice — void or delete the invoice first.');
        }

        $billable->delete();
    }
}
