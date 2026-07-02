<?php

namespace App\Support;

/**
 * Jurisdictions used across the system. Includes regional IP offices
 * (EP, WO, EU/EUIPO) alongside ISO 3166-1 alpha-2 countries.
 */
class Countries
{
    private const LIST = [
        'AE' => 'United Arab Emirates',
        'AR' => 'Argentina',
        'AT' => 'Austria',
        'AU' => 'Australia',
        'BE' => 'Belgium',
        'BR' => 'Brazil',
        'CA' => 'Canada',
        'CH' => 'Switzerland',
        'CL' => 'Chile',
        'CN' => 'China',
        'CO' => 'Colombia',
        'CZ' => 'Czechia',
        'DE' => 'Germany',
        'DK' => 'Denmark',
        'EG' => 'Egypt',
        'EP' => 'European Patent Office (EPO)',
        'ES' => 'Spain',
        'EU' => 'European Union (EUIPO)',
        'FI' => 'Finland',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'GR' => 'Greece',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'ID' => 'Indonesia',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IN' => 'India',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'MX' => 'Mexico',
        'MY' => 'Malaysia',
        'NL' => 'Netherlands',
        'NO' => 'Norway',
        'NZ' => 'New Zealand',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RU' => 'Russia',
        'SA' => 'Saudi Arabia',
        'SE' => 'Sweden',
        'SG' => 'Singapore',
        'TH' => 'Thailand',
        'TR' => 'Türkiye',
        'TW' => 'Taiwan',
        'US' => 'United States',
        'VN' => 'Vietnam',
        'WO' => 'WIPO (PCT / Madrid / Hague)',
        'ZA' => 'South Africa',
    ];

    public static function all(): array
    {
        return self::LIST;
    }

    public static function name(?string $code): ?string
    {
        return $code ? (self::LIST[strtoupper($code)] ?? $code) : null;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn ($code, $name) => ['value' => $code, 'label' => "{$code} — {$name}"],
            array_keys(self::LIST),
            self::LIST
        );
    }
}
