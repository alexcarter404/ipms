<?php

namespace App\Support;

/**
 * The catalogue of matter fields a workflow stage can demand in its
 * data contract. Keys are matter attributes; the take-on flow marks
 * them required when entering at (or beyond) a stage that lists them.
 */
class ContractFields
{
    private const FIELDS = [
        'application_no' => 'Application number',
        'application_date' => 'Application (filing) date',
        'publication_no' => 'Publication number',
        'publication_date' => 'Publication date',
        'registration_no' => 'Registration / grant number',
        'registration_date' => 'Registration / grant date',
        'priority_no' => 'Priority number',
        'priority_date' => 'Priority date',
        'expiry_date' => 'Expiry date',
        'filing_route' => 'Filing route',
        'responsible_user_id' => 'Responsible attorney',
        'client_entity_id' => 'Billing entity',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    public static function label(string $key): string
    {
        return self::FIELDS[$key] ?? $key;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn ($key, $label) => ['value' => $key, 'label' => $label],
            array_keys(self::FIELDS),
            self::FIELDS
        );
    }
}
