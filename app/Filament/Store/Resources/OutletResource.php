<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Concerns\RestrictsToOwner;
use App\Filament\Store\Resources\OutletResource\Pages;
use App\Models\Outlet;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OutletResource extends Resource
{
    use RestrictsToOwner;

    protected static ?string $model = Outlet::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Outlets';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true),

            TextInput::make('address_line_1')->required()->maxLength(255),
            TextInput::make('address_line_2')->maxLength(255),
            TextInput::make('city')->required()->maxLength(100),
            TextInput::make('phone')->tel()->maxLength(30),
            TextInput::make('email')->email()->maxLength(255),

            KeyValue::make('opening_hours')
                ->keyLabel('Day')
                ->valueLabel('Hours')
                ->helperText('Add one row per day, e.g. key "saturday" value "10:00 AM - 8:00 PM". Leave empty to hide the hours block.')
                ->columnSpanFull(),

            TextInput::make('latitude')->numeric()->step(0.0000001)->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->numeric()->step(0.0000001)->minValue(-180)->maxValue(180),
            TextInput::make('map_url')->url()->maxLength(500)
                ->helperText('Optional Google Maps link. Takes priority over coordinates.'),

            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('city')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('sort_order'),
            ])
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No outlets yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutlets::route('/'),
            'create' => Pages\CreateOutlet::route('/create'),
            'edit' => Pages\EditOutlet::route('/{record}/edit'),
        ];
    }
}
