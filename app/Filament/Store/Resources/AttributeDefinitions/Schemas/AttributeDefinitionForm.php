<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AttributeDefinitions\Schemas;

use App\Enums\AttributeDataType;
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
                    ->disabled(fn (?\App\Models\AttributeDefinition $record): bool => $record?->hasRecordedValues() ?? false)
                    ->helperText(fn (?\App\Models\AttributeDefinition $record): ?string => $record?->hasRecordedValues() ? 'Locked — values already recorded against this attribute.' : null),

                TextInput::make('unit')
                    ->helperText('e.g. GB, inch, mAh'),

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