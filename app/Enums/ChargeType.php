<?php

namespace App\Enums;

enum ChargeType: string
{
    case FixedFee = 'fixed_fee';
    case StagePayment = 'stage_payment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FixedFee => 'Fixed Fee',
            self::StagePayment => 'Stage Payment',
            self::Other => 'Other Charge',
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
