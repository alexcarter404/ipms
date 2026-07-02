<?php

namespace App\Enums;

use App\Models\Matter;
use Carbon\CarbonInterface;

enum TriggerEvent: string
{
    case Filing = 'filing';
    case Publication = 'publication';
    case Grant = 'grant';
    case Registration = 'registration';
    case Priority = 'priority';
    case OfficeAction = 'office_action';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Filing => 'Filing Date',
            self::Publication => 'Publication Date',
            self::Grant => 'Grant Date',
            self::Registration => 'Registration Date',
            self::Priority => 'Priority Date',
            self::OfficeAction => 'Office Action',
            self::Manual => 'Manual Date',
        };
    }

    /** Resolve the trigger's base date from a matter, when it can be derived. */
    public function baseDate(Matter $matter): ?CarbonInterface
    {
        return match ($this) {
            self::Filing => $matter->application_date,
            self::Publication => $matter->publication_date,
            self::Grant, self::Registration => $matter->registration_date,
            self::Priority => $matter->priority_date,
            self::OfficeAction, self::Manual => null,
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
