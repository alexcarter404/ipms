<?php

namespace App\Enums;

enum BillableStatus: string
{
    case Billable = 'billable';
    case Billed = 'billed';
    case WrittenOff = 'written_off';
    case NonBillable = 'non_billable';

    public function label(): string
    {
        return match ($this) {
            self::Billable => 'Billable',
            self::Billed => 'Billed',
            self::WrittenOff => 'Written Off',
            self::NonBillable => 'Non-billable',
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
