<?php

namespace App\Actions\Billing;

use App\Enums\BillableStatus;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;

/**
 * Write off / reinstate a WIP item (time entry, disbursement or charge).
 * Billed items are locked to their invoice.
 */
class UpdateBillableStatus
{
    public function handle(Model $billable, BillableStatus $status): Model
    {
        if ($billable->status === BillableStatus::Billed) {
            throw new DomainActionException('This item is on an invoice — void or delete the invoice first.');
        }

        if ($status === BillableStatus::Billed) {
            throw new DomainActionException('Items are marked billed by drafting an invoice, not directly.');
        }

        $billable->update(['status' => $status]);

        return $billable;
    }
}
