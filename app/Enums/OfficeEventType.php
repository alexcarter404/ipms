<?php

namespace App\Enums;

enum OfficeEventType: string
{
    case Publication = 'publication';
    case Grant = 'grant';
    case Registration = 'registration';
    case OfficeAction = 'office_action';
    case RenewalReminder = 'renewal_reminder';

    public function label(): string
    {
        return match ($this) {
            self::Publication => 'Publication',
            self::Grant => 'Grant',
            self::Registration => 'Registration',
            self::OfficeAction => 'Office Action',
            self::RenewalReminder => 'Renewal Reminder',
        };
    }

    /** The workflow trigger this office event corresponds to, if any. */
    public function trigger(): ?TriggerEvent
    {
        return match ($this) {
            self::Publication => TriggerEvent::Publication,
            self::Grant => TriggerEvent::Grant,
            self::Registration => TriggerEvent::Registration,
            self::OfficeAction => TriggerEvent::OfficeAction,
            self::RenewalReminder => null,
        };
    }

    /** The matter status this event moves a matter to, if any. */
    public function matterStatus(): ?MatterStatus
    {
        return match ($this) {
            self::Publication => MatterStatus::Published,
            self::Grant => MatterStatus::Granted,
            self::Registration => MatterStatus::Registered,
            self::OfficeAction => MatterStatus::OfficeAction,
            self::RenewalReminder => null,
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
