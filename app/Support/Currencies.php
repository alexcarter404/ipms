<?php

namespace App\Support;

class Currencies
{
    private const NAMES = [
        'GBP' => 'Pound Sterling',
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
        'JPY' => 'Japanese Yen',
        'CNY' => 'Chinese Yuan',
        'CHF' => 'Swiss Franc',
        'AUD' => 'Australian Dollar',
        'CAD' => 'Canadian Dollar',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return config('billing.currencies');
    }

    public static function base(): string
    {
        return config('billing.base_currency');
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (string $code) => [
                'value' => $code,
                'label' => isset(self::NAMES[$code]) ? "{$code} — ".self::NAMES[$code] : $code,
            ],
            self::codes()
        );
    }
}
