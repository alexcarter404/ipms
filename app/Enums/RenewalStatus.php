<?php

namespace App\Enums;

enum RenewalStatus: string
{
    case Upcoming = 'upcoming';
    case ReminderSent = 'reminder_sent';
    case Instructed = 'instructed';
    case Paid = 'paid';
    case Lapsed = 'lapsed';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::ReminderSent => 'Reminder Sent',
            self::Instructed => 'Instructed',
            self::Paid => 'Paid',
            self::Lapsed => 'Lapsed',
            self::Waived => 'Waived',
        };
    }

    /** Still needs action before the due date. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Upcoming, self::ReminderSent, self::Instructed], true);
    }

    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }
}
