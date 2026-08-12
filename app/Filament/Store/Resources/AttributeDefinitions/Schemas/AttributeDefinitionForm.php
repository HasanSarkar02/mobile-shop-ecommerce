<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AttributeDefinitions\Schemas;

use App\Enums\AttributeDataType;
use App\Models\AttributeDefinition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AttributeDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->alphaDash(),

                TextInput::make('label')
                    ->required(),

                Select::make('data_type')
                    ->options(collect(AttributeDataType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required()
                    ->disabled(fn (?AttributeDefinition $record): bool => $record?->hasRecordedValues() ?? false)
                    ->helperText(fn (?AttributeDefinition $record): ?string => $record?->hasRecordedValues() ? 'Locked — values already recorded against this attribute.' : null),

                TextInput::make('unit')
                    ->helperText('e.g. GB, inch, mAh'),

                TextInput::make('group')
                    ->placeholder('e.g. Display, Battery, Dimensions')
                    ->helperText('Specification section heading shown on the product page. Leave empty to fall back to General.'),

                TextInput::make('group_sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Orders this spec group relative to other groups (lower first).'),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Orders this attribute within its group/secondly across the whole list (lower first).'),

                Toggle::make('is_filterable')
                    ->default(true),

                Toggle::make('is_variant_defining'),

                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
            ]);
    }
}
