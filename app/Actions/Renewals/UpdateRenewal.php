<?php

namespace App\Actions\Renewals;

use App\Enums\RenewalStatus;
use App\Models\Renewal;

class UpdateRenewal
{
    /** Status transitions stamp when instruction/payment happened. */
    public function handle(Renewal $renewal, array $data): Renewal
    {
        if (isset($data['status'])) {
            $newStatus = RenewalStatus::from($data['status']);

            if ($newStatus === RenewalStatus::Instructed && $renewal->status !== RenewalStatus::Instructed) {
                $data['instructed_at'] = now();
            }

            if ($newStatus === RenewalStatus::Paid && $renewal->status !== RenewalStatus::Paid) {
                $data['paid_at'] = now();
            }
        }

        $renewal->update($data);

        return $renewal;
    }
}
