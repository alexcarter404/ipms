<?php

namespace App\Enums;

enum ContactType: string
{
    case Person = 'person';
    case Mailbox = 'mailbox';
    case Organisation = 'organisation';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Person',
            self::Mailbox => 'Mailbox / Docketing',
            self::Organisation => 'Organisation',
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
