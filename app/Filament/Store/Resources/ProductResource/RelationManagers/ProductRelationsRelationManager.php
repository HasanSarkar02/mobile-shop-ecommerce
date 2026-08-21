<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\RelationManagers;

use App\Enums\ProductRelationType;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductRelationsRelationManager extends RelationManager
{
    protected static string $relationship = 'productRelations';

    protected static ?string $title = 'Related Products';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('related_product_id')
                ->label('Product')
                ->options(fn () => Product::query()
                    ->where('id', '!=', $this->getOwnerRecord()->id)
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (Product $product) => [$product->id => $product->name ?? "#{$product->id}"]))
                ->searchable()
                ->required(),
            Select::make('type')
                ->options(collect(ProductRelationType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->required(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('relatedProduct.name')->label('Product'),
                TextColumn::make('type')->badge(),
                TextColumn::make('sort_order'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([DeleteAction::make()]);
    }
}
