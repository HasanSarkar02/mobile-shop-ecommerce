<?php

namespace App\Filament\Store\Resources\AttributeDefinitions;

use App\Filament\Store\Resources\AttributeDefinitions\Pages\CreateAttributeDefinition;
use App\Filament\Store\Resources\AttributeDefinitions\Pages\EditAttributeDefinition;
use App\Filament\Store\Resources\AttributeDefinitions\Pages\ListAttributeDefinitions;
use App\Filament\Store\Resources\AttributeDefinitions\Schemas\AttributeDefinitionForm;
use App\Filament\Store\Resources\AttributeDefinitions\Tables\AttributeDefinitionsTable;
use App\Models\AttributeDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttributeDefinitionResource extends Resource
{
    protected static ?string $model = AttributeDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return AttributeDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributeDefinitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributeDefinitions::route('/'),
            'create' => CreateAttributeDefinition::route('/create'),
            'edit' => EditAttributeDefinition::route('/{record}/edit'),
        ];
    }
}
