<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\CourierProviderResource\Pages;
use App\Models\CourierProvider;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CourierProviderResource extends Resource
{
    protected static ?string $model = CourierProvider::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true)->helperText('steadfast, pathao, etc.'),
            TextInput::make('name')->required(),
            TextInput::make('display_name')->label('Display Name'),
            TextInput::make('base_url')->label('Base URL (default)')->url(),
            TextInput::make('base_url_sandbox')->label('Sandbox Base URL')->url(),
            TextInput::make('base_url_live')->label('Live Base URL')->url(),
            Select::make('auth_type')
                ->options(['api_key' => 'Api-Key + Secret-Key', 'oauth' => 'OAuth (client_id/secret)', 'bearer' => 'Bearer Token'])
                ->required()
                ->default('api_key'),
            Textarea::make('required_fields')
                ->label('Required Fields (JSON)')
                ->helperText('JSON array, e.g. ["api_key","secret_key"] or ["client_id","client_secret","username","password"]')
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null)
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('driver_class')->label('Driver Class')->helperText('FQCN, e.g. App\Services\Shipping\SteadfastDriver'),
            Toggle::make('is_active')->default(true),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('auth_type')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order'),
            ])
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourierProviders::route('/'),
            'create' => Pages\CreateCourierProvider::route('/create'),
            'edit' => Pages\EditCourierProvider::route('/{record}/edit'),
        ];
    }
}
