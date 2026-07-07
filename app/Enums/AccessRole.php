<?php

namespace App\Enums;

/**
 * What a user may do in the system — orthogonal to their timekeeper
 * grade (which prices their time).
 */
enum AccessRole: string
{
    case Admin = 'admin';
    case Professional = 'professional';
    case Finance = 'finance';
    case ReadOnly = 'readonly';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Professional => 'Professional',
            self::Finance => 'Finance',
            self::ReadOnly => 'Read-only',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Everything, including settings, workflows, templates, users and walled clients',
            self::Professional => 'Day-to-day matter, docket and billing work',
            self::Finance => 'Billing operations plus billing settings (rates, taxes, codes)',
            self::ReadOnly => 'Sees everything they are allowed to, changes nothing',
        };
    }

    /** @return list<array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases()
        );
    }
}
