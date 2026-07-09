<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\RelationManagers;

use App\Enums\VariantAvailability;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('barcode'),
            TextInput::make('color'),
            TextInput::make('storage_gb')->numeric()->suffix('GB'),
            TextInput::make('ram_gb')->numeric()->suffix('GB'),
            Select::make('sim_type')->options([
                'Single SIM' => 'Single SIM',
                'Dual SIM' => 'Dual SIM',
                'eSIM' => 'eSIM',
                'Dual SIM + eSIM' => 'Dual SIM + eSIM',
            ]),
            TextInput::make('price')
                ->label('Price (BDT)')
                ->numeric()
                ->required()
                ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state): int => (int) round(($state ?? 0) * 100)),
            TextInput::make('compare_at_price')
                ->label('Original price (BDT) — optional, shows as discount')
                ->numeric()
                ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state): ?int => $state !== null ? (int) round($state * 100) : null),
            Select::make('availability')
                ->options(collect(VariantAvailability::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(VariantAvailability::InStock->value)
                ->required()
                ->live(),
            DateTimePicker::make('expected_available_at')
                ->label('Expected availability date')
                ->visible(fn (Get $get): bool => $get('availability') === VariantAvailability::PreOrder->value),
            SpatieMediaLibraryFileUpload::make('images')
                ->collection('images')
                ->image()
                ->multiple()
                ->reorderable()
                ->helperText('Photos specific to this color/variant. Leave empty to use the product\'s general photos.'),
            TextInput::make('weight_grams')->numeric()->suffix('g'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('sku'),
                TextColumn::make('color')->placeholder('—'),
                TextColumn::make('storage_gb')->suffix(' GB')->placeholder('—'),
                TextColumn::make('ram_gb')->suffix(' GB')->placeholder('—'),
                TextColumn::make('sim_type')->placeholder('—'),
                TextColumn::make('price')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
                TextColumn::make('compare_at_price')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state, $record): string => $record->discountPercentage() ? $record->discountPercentage().'% off' : '—'),
                TextColumn::make('availability')->badge(),
                TextColumn::make('expected_available_at')->date()->placeholder('—'),
                TextColumn::make('is_active')->badge(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}