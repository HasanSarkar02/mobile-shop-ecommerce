<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\PaymentMethodType;
use App\Filament\Store\Concerns\RestrictsToOwner;
use App\Filament\Store\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use Filament\Schemas\Components\Utilities\Get;

class PaymentMethodResource extends Resource
{
    use RestrictsToOwner;
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Select::make('type')
                ->options(collect(PaymentMethodType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->required(),
            Select::make('gateway_driver')
                ->label('Gateway Driver')
                ->options(array_combine(array_keys(config('payment_gateways.drivers')), array_keys(config('payment_gateways.drivers'))))
                ->visible(fn (Get $get): bool => $get('type') === 'aggregator')
                ->helperText('Only required when Type is "Payment Aggregator".'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}