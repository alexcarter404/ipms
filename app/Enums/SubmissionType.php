<?php

namespace App\Enums;

enum SubmissionType: string
{
    case Filing = 'filing';
    case OaResponse = 'oa_response';
    case RenewalPayment = 'renewal_payment';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Filing => 'New Filing',
            self::OaResponse => 'Office Action Response',
            self::RenewalPayment => 'Renewal Payment',
            self::Document => 'Document / Form',
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
