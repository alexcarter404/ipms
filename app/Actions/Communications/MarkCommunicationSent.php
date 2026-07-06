<?php

namespace App\Actions\Communications;

use App\Exceptions\DomainActionException;
use App\Mail\CommunicationMail;
use App\Models\Communication;
use Illuminate\Support\Facades\Mail;

class MarkCommunicationSent
{
    /** @return array{communication: Communication, delivered: bool} */
    public function handle(Communication $communication): array
    {
        if ($communication->status === 'sent') {
            throw new DomainActionException('Communication already sent.');
        }

        // Email comms with a recipient actually go out; letters (and
        // emails without an address) are just recorded as sent.
        $delivered = false;
        if ($communication->channel === 'email' && $communication->recipient_email) {
            Mail::to($communication->recipient_email, $communication->recipient_name)
                ->send(new CommunicationMail($communication));
            $delivered = true;
        }

        $communication->update(['status' => 'sent', 'sent_at' => now()]);

        return ['communication' => $communication, 'delivered' => $delivered];
    }
}
