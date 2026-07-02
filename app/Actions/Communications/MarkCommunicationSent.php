<?php

namespace App\Actions\Communications;

use App\Exceptions\DomainActionException;
use App\Models\Communication;

class MarkCommunicationSent
{
    public function handle(Communication $communication): Communication
    {
        if ($communication->status === 'sent') {
            throw new DomainActionException('Communication already sent.');
        }

        $communication->update(['status' => 'sent', 'sent_at' => now()]);

        return $communication;
    }
}
