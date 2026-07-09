<?php

declare(strict_types=1);

namespace App\Enums;

enum AttributeDataType: string
{
    case Text = 'text';
    case Number = 'number';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Select = 'select';
    case MultiSelect = 'multiselect';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Decimal => 'Decimal',
            self::Boolean => 'Yes/No',
            self::Select => 'Select (single)',
            self::MultiSelect => 'Select (multiple)',
        };
    }
}