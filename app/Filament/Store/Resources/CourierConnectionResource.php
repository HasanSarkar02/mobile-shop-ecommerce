<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Concerns\RestrictsToOwner;
use App\Filament\Store\Resources\CourierConnectionResource\Pages;
use App\Models\CourierConnection;
use App\Models\CourierProvider;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CourierConnectionResource extends Resource
{
    use RestrictsToOwner;

    protected static ?string $model = CourierConnection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('courier_provider_id')
                ->label('Courier Provider')
                ->options(fn (): array => CourierProvider::query()->where('is_active', true)->pluck('display_name', 'id')->all())
                ->helperText('Choose a courier registered by the platform. Contact platform admin if your courier is missing.')
                ->required()
                ->searchable(),

            Toggle::make('sandbox')->label('Sandbox Mode')->helperText('Use sandbox credentials for testing.')->default(true),

            Toggle::make('is_active')->label('Active')->default(true),

            Toggle::make('is_default')->label('Default Courier')->helperText('Used for one-click shipment when multiple are active.'),

            KeyValue::make('credentials')
                ->label('Credentials')
                ->helperText('Keys depend on provider: Steadfast needs api_key + secret_key; Pathao needs client_id, client_secret, username, password. Values are encrypted at rest.')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->columnSpanFull()
                ->addActionLabel('Add credential'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider.display_name')->label('Provider')->searchable(),
                TextColumn::make('provider.code')->label('Code')->badge(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                IconColumn::make('sandbox')->boolean()->label('Sandbox'),
                IconColumn::make('is_default')->boolean()->label('Default'),
            ])
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourierConnections::route('/'),
            'create' => Pages\CreateCourierConnection::route('/create'),
            'edit' => Pages\EditCourierConnection::route('/{record}/edit'),
        ];
    }
}
