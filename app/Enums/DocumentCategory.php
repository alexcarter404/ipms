<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case OfficeAction = 'office_action';
    case FiledDocument = 'filed_document';
    case Receipt = 'receipt';
    case Correspondence = 'correspondence';
    case Evidence = 'evidence';
    case Generated = 'generated';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OfficeAction => 'Office Action',
            self::FiledDocument => 'Filed Document',
            self::Receipt => 'Receipt',
            self::Correspondence => 'Correspondence',
            self::Evidence => 'Evidence',
            self::Generated => 'Generated',
            self::Other => 'Other',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
