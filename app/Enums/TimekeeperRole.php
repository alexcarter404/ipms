<?php

namespace App\Enums;

enum TimekeeperRole: string
{
    case Partner = 'partner';
    case Attorney = 'attorney';
    case CaseManager = 'case_manager';
    case Paralegal = 'paralegal';

    public function label(): string
    {
        return match ($this) {
            self::Partner => 'Partner',
            self::Attorney => 'Attorney',
            self::CaseManager => 'Case Manager',
            self::Paralegal => 'Paralegal',
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
