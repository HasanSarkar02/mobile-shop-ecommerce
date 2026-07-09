<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\EmiPlanResource\Pages;
use App\Models\EmiPlan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EmiPlanResource extends Resource
{
    protected static ?string $model = EmiPlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'EMI Plans';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('bank_name')->required(),
            TextInput::make('tenure_months')->numeric()->required()->suffix('months'),
            TextInput::make('interest_rate')->numeric()->required()->suffix('%'),
            Toggle::make('active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_name')->searchable(),
                TextColumn::make('tenure_months')->suffix(' months'),
                TextColumn::make('interest_rate')->suffix('%'),
                IconColumn::make('active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmiPlans::route('/'),
            'create' => Pages\CreateEmiPlan::route('/create'),
            'edit' => Pages\EditEmiPlan::route('/{record}/edit'),
        ];
    }
}