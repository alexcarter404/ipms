<?php

namespace App\Actions\Communications;

use App\Exceptions\DomainActionException;
use App\Models\Communication;

class DeleteCommunication
{
    public function handle(Communication $communication): void
    {
        if ($communication->status === 'sent') {
            throw new DomainActionException('Sent communications cannot be deleted.');
        }

        $communication->delete();
    }
}
