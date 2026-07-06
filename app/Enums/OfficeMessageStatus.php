<?php

namespace App\Enums;

enum OfficeMessageStatus: string
{
    case NeedsReview = 'needs_review';
    case Matched = 'matched';
    case Processed = 'processed';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::NeedsReview => 'Needs Review',
            self::Matched => 'Matched',
            self::Processed => 'Processed',
            self::Dismissed => 'Dismissed',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }
}
