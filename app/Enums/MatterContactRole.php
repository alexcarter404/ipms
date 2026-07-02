<?php

namespace App\Enums;

enum MatterContactRole: string
{
    case Main = 'main';
    case Docketing = 'docketing';
    case Billing = 'billing';
    case Reporting = 'reporting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Main Contact',
            self::Docketing => 'Docketing',
            self::Billing => 'Billing',
            self::Reporting => 'Reporting',
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
