<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CouponResource\RelationManagers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScopesRelationManager extends RelationManager
{
    protected static string $relationship = 'scopes';

    protected static ?string $title = 'Scope (Specific Products/Categories/Brands/Collections)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('scopable_type')
                ->label('Type')
                ->options(['product' => 'Product', 'category' => 'Category', 'brand' => 'Brand', 'collection' => 'Collection'])
                ->required()
                ->live(),
            Select::make('scopable_id')
                ->label('Item')
                ->options(fn (Get $get) => match ($get('scopable_type')) {
                    'product' => Product::query()->with('translations')->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->name ?? "#{$p->id}"]),
                    'category' => Category::query()->pluck('name', 'id'),
                    'brand' => Brand::query()->pluck('name', 'id'),
                    'collection' => Collection::query()->pluck('name', 'id'),
                    default => [],
                })
                ->searchable()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('scopable_id')
            ->columns([
                TextColumn::make('scopable_type')->badge(),
                TextColumn::make('scopable_id')->label('Item ID'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([DeleteAction::make()]);
    }
}
