<?php

namespace App\Enums;

enum PartyRole: string
{
    case Applicant = 'applicant';
    case Inventor = 'inventor';
    case Owner = 'owner';
    case Agent = 'agent';
    case Associate = 'associate';
    case Licensee = 'licensee';
    case Opponent = 'opponent';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }
}
