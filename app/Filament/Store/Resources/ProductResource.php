<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\ProductType;
use App\Filament\Store\Resources\ProductResource\Pages;
use App\Filament\Store\Resources\ProductResource\RelationManagers\AttributeValuesRelationManager;
use App\Filament\Store\Resources\ProductResource\RelationManagers\ProductRelationsRelationManager;
use App\Filament\Store\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Repeater::make('translations')
                ->relationship('translations')
                ->schema([
                    Select::make('locale')->options(['en' => 'English', 'bn' => 'Bangla'])->required(),
                    TextInput::make('name')->required(),
                    TextInput::make('slug')->required(),
                    Textarea::make('description')->rows(4),
                    Textarea::make('warranty_info')->rows(3)->helperText('Warranty terms shown in the Warranty tab.'),
                    TextInput::make('meta_title'),
                    Textarea::make('meta_description')->rows(2),
                ])
                ->defaultItems(1)
                ->addActionLabel('Add language')
                ->columns(1),
            Select::make('brand_id')->relationship('brand', 'name')->searchable()->preload(),
            Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
            TextInput::make('model_number'),
            Select::make('type')
                ->options(collect(ProductType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(ProductType::Simple->value)
                ->required(),
            Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->required()->default('draft'),
            Toggle::make('is_featured'),
            Toggle::make('is_serialized')->helperText('Enable for products requiring IMEI/serial tracking.'),
            Select::make('tags')->relationship('tags', 'name')->multiple()->preload(),
            Select::make('emiPlans')->relationship('emiPlans', 'bank_name')->multiple()->preload(),
            SpatieMediaLibraryFileUpload::make('images')->collection('images')->image()->multiple()->reorderable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')->collection('images')->conversion('thumb'),
                TextColumn::make('name')->label('Name')->limit(40),
                TextColumn::make('brand.name'),
                TextColumn::make('category.name'),
                TextColumn::make('base_price')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
                TextColumn::make('status')->badge(),
                TextColumn::make('variants_count')->counts('variants')->label('Variants'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [VariantsRelationManager::class, AttributeValuesRelationManager::class, ProductRelationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}