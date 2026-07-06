<?php

namespace App\Repositories;

use App\Models\Matter;

/**
 * Work-in-progress queries: the unbilled value sitting on a matter.
 */
class WipRepository
{
    /** @return array{time: float, disbursements: float, charges: float, total: float, currency: string} */
    public function totals(Matter $matter): array
    {
        $time = (float) $matter->timeEntries()->billable()->sum('amount');
        $disbursements = (float) $matter->disbursements()->billable()->sum('amount');
        $charges = (float) $matter->charges()->billable()->sum('amount');

        return [
            'time' => $time,
            'disbursements' => $disbursements,
            'charges' => $charges,
            'total' => round($time + $disbursements + $charges, 2),
            'currency' => $matter->billingCurrency(),
        ];
    }

    /** Everything recorded against the matter, for the billing tab. */
    public function forMatter(Matter $matter): array
    {
        return [
            'timeEntries' => $matter->timeEntries()
                ->with(['user:id,name', 'activityCode:id,code,description'])
                ->limit(100)->get(),
            'disbursements' => $matter->disbursements()->limit(100)->get(),
            'charges' => $matter->charges()->with('stage:id,description')->limit(100)->get(),
            'invoices' => $matter->invoices()->with('entity:id,name')->get(),
        ];
    }
}
