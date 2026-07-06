<?php

namespace App\Enums;

enum AgreementType: string
{
    case Hourly = 'hourly';
    case Blended = 'blended';
    case Capped = 'capped';
    case Fixed = 'fixed';
    case Stage = 'stage';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Blended => 'Blended Hourly',
            self::Capped => 'Capped Fee',
            self::Fixed => 'Fixed / Flat Fee',
            self::Stage => 'Stage Payments',
        };
    }

    /** Whether recorded time is billed to the client under this arrangement. */
    public function billsTime(): bool
    {
        return in_array($this, [self::Hourly, self::Blended, self::Capped], true);
    }

    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }
}
