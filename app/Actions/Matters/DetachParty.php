<?php

namespace App\Actions\Matters;

use App\Models\Matter;
use App\Models\Party;

class DetachParty
{
    public function handle(Matter $matter, Party $party, string $role): void
    {
        $matter->parties()->wherePivot('role', $role)->detach($party->id);
    }
}
