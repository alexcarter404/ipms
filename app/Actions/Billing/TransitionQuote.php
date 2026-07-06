<?php

namespace App\Actions\Billing;

use App\Enums\QuoteStatus;
use App\Exceptions\DomainActionException;
use App\Models\Quote;

class TransitionQuote
{
    private const ALLOWED = [
        'draft' => ['sent', 'accepted', 'declined'],
        'sent' => ['accepted', 'declined'],
        'accepted' => [],
        'declined' => [],
    ];

    public function handle(Quote $quote, QuoteStatus $to): Quote
    {
        if (! in_array($to->value, self::ALLOWED[$quote->status->value], true)) {
            throw new DomainActionException(
                "A {$quote->status->label()} quote cannot be marked {$to->label()}."
            );
        }

        $quote->update(['status' => $to]);

        return $quote;
    }
}
