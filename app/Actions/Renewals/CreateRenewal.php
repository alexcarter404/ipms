<?php

namespace App\Actions\Renewals;

use App\Enums\RenewalStatus;
use App\Models\Matter;
use App\Models\Renewal;

class CreateRenewal
{
    public function handle(Matter $matter, array $data): Renewal
    {
        return $matter->renewals()->create($data + ['status' => RenewalStatus::Upcoming]);
    }
}
