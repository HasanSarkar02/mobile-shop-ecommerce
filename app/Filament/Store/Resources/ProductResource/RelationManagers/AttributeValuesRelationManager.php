<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\RelationManagers;

use App\Enums\AttributeDataType;
use App\Models\AttributeDefinition;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributeValues';

    protected static ?string $title = 'Specifications';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_variant_id')
                ->label('Variant')
                ->options(fn () => $this->getOwnerRecord()?->variants()->pluck('sku', 'id')->all() ?? [])
                ->searchable()
                ->placeholder('Product-level attribute')
                ->helperText('Assign to a variant when this value describes a specific variation (e.g. Size, Color, Weight). Leave empty for product-level specifications.'),
            Select::make('attribute_definition_id')
                ->label('Attribute')
                ->options(fn () => AttributeDefinition::query()->pluck('label', 'id'))
                ->searchable()
                ->required()
                ->live(),
            TextInput::make('value_string')
                ->label('Value')
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Text),
            TextInput::make('value_integer')
                ->label('Value')
                ->numeric()
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Number),
            TextInput::make('value_decimal')
                ->label('Value')
                ->numeric()
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Decimal),
            Toggle::make('value_boolean')
                ->label('Value')
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Boolean),
            Select::make('attribute_option_id')
                ->label('Value')
                ->options(function (Get $get): array {
                    $attribute = AttributeDefinition::find($get('attribute_definition_id'));

                    return $attribute?->options()->pluck('label', 'id')->all() ?? [];
                })
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Select),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('variant.sku')->label('Variant')->placeholder('Product'),
                TextColumn::make('attributeDefinition.label')->label('Attribute'),
                TextColumn::make('value_string')->label('Value'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    private function dataTypeOf(?int $attributeDefinitionId): ?AttributeDataType
    {
        if (! $attributeDefinitionId) {
            return null;
        }

        return AttributeDefinition::find($attributeDefinitionId)?->data_type;
    }
}
