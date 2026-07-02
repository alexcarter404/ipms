<?php

namespace App\Enums;

enum MatterType: string
{
    case Patent = 'patent';
    case Trademark = 'trademark';
    case Design = 'design';
    case Copyright = 'copyright';
    case Domain = 'domain';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Patent => 'Patent',
            self::Trademark => 'Trade Mark',
            self::Design => 'Design',
            self::Copyright => 'Copyright',
            self::Domain => 'Domain Name',
            self::Other => 'Other',
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
