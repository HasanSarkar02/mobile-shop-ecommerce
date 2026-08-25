<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantIndustry: string
{
    case General = 'general';
    case Electronics = 'electronics';
    case Mobile = 'mobile';
    case Fashion = 'fashion';
    case Grocery = 'grocery';
    case Sports = 'sports';
    case Furniture = 'furniture';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Electronics => 'Electronics',
            self::Mobile => 'Mobile',
            self::Fashion => 'Fashion',
            self::Grocery => 'Grocery',
            self::Sports => 'Sports',
            self::Furniture => 'Furniture',
        };
    }

    /**
     * @return array<string, string> value => label
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
