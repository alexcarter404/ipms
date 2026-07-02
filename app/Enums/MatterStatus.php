<?php

namespace App\Enums;

enum MatterStatus: string
{
    case Draft = 'draft';
    case PendingFiling = 'pending_filing';
    case Filed = 'filed';
    case Published = 'published';
    case UnderExamination = 'under_examination';
    case OfficeAction = 'office_action';
    case Accepted = 'accepted';
    case Granted = 'granted';
    case Registered = 'registered';
    case Opposed = 'opposed';
    case Abandoned = 'abandoned';
    case Lapsed = 'lapsed';
    case Expired = 'expired';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingFiling => 'Pending Filing',
            self::Filed => 'Filed',
            self::Published => 'Published',
            self::UnderExamination => 'Under Examination',
            self::OfficeAction => 'Office Action',
            self::Accepted => 'Accepted',
            self::Granted => 'Granted',
            self::Registered => 'Registered',
            self::Opposed => 'Opposed',
            self::Abandoned => 'Abandoned',
            self::Lapsed => 'Lapsed',
            self::Expired => 'Expired',
            self::Closed => 'Closed',
        };
    }

    /** Statuses considered live/active for reporting and renewals. */
    public function isActive(): bool
    {
        return ! in_array($this, [
            self::Abandoned, self::Lapsed, self::Expired, self::Closed,
        ], true);
    }

    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }
}
