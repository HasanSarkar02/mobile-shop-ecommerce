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
                    ->options(
                        collect(AttributeDataType::cases())
                            ->mapWithKeys(fn (AttributeDataType $case) => [
                                $case->value => $case->label(),
                            ])
                            ->all()
                    )
                    ->required(),

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