<?php

namespace App\Actions\Billing;

use App\Enums\BillableStatus;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;

/**
 * Amend a WIP item's wording (narrative / description) before it is
 * billed — invoice lines are built from these, so billed items are
 * locked to their invoice.
 */
class AmendBillable
{
    public function handle(Model $billable, array $data): Model
    {
        if ($billable->status === BillableStatus::Billed) {
            throw new DomainActionException('This item is on an invoice — void or delete the invoice first.');
        }

        $billable->update($data);

        return $billable;
    }
}
