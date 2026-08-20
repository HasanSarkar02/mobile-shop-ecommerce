<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\SerialNumberStatus;
use App\Filament\Store\Resources\SerialNumberResource\Pages;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SerialNumberResource extends Resource
{
    protected static ?string $model = SerialNumber::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Serial / IMEI Numbers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_variant_id')
                ->label('Variant (SKU)')
                ->options(fn () => ProductVariant::query()->pluck('sku', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('imei_or_serial')->required()->scopedUnique(ignoreRecord: true),
            Select::make('status')
                ->options(collect(SerialNumberStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(SerialNumberStatus::Available->value)
                ->required(),
            DateTimePicker::make('warranty_start_at'),
            DateTimePicker::make('warranty_end_at'),
            Textarea::make('notes')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('imei_or_serial')->searchable()->label('IMEI/Serial'),
                TextColumn::make('variant.sku')->label('SKU'),
                TextColumn::make('status')->badge(),
                TextColumn::make('warranty_end_at')->date()->label('Warranty Until')->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSerialNumbers::route('/'),
            'create' => Pages\CreateSerialNumber::route('/create'),
            'edit' => Pages\EditSerialNumber::route('/{record}/edit'),
        ];
    }
}
