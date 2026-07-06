<?php

namespace App\Services\Integrations;

use App\Models\Matter;
use App\Models\OfficeMessage;

/**
 * Matches an inbound office message to a matter by application or
 * registration number, tolerant of the formatting differences between
 * office and docket ("GB2101234.5" vs "GB 21 01234.5").
 */
class MessageMatcher
{
    public function match(OfficeMessage $message): ?Matter
    {
        $application = self::normalise($message->application_no);
        $registration = self::normalise($message->registration_no);

        if (! $application && ! $registration) {
            return null;
        }

        $candidates = Matter::query()
            ->whereNotNull('application_no')
            ->orWhereNotNull('registration_no')
            ->get(['id', 'application_no', 'registration_no'])
            ->filter(fn (Matter $matter) => ($application && self::normalise($matter->application_no) === $application)
                || ($registration && self::normalise($matter->registration_no) === $registration));

        // Only an unambiguous match is safe to automate against.
        return $candidates->count() === 1
            ? Matter::find($candidates->first()->id)
            : null;
    }

    public static function normalise(?string $number): ?string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $number ?? ''));

        return $clean !== '' ? $clean : null;
    }
}
