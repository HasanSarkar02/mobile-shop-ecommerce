<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\NotificationTemplateResource\Pages;
use App\Models\NotificationTemplate;
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

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Notifications';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('event_key')
                ->required()
                ->helperText('e.g. order.placed, order.status_changed, order.cancelled, payment.recorded'),
            Select::make('channel')
                ->options(array_combine(array_keys(config('notification_channels.drivers')), array_keys(config('notification_channels.drivers'))))
                ->required(),
            TextInput::make('subject')->helperText('Leave empty for SMS.'),
            Textarea::make('body')
                ->required()
                ->rows(6)
                ->helperText('Placeholders: {{ order.number }} {{ order.total }} {{ order.status }} {{ customer.name }} {{ customer.email }} {{ store.name }} {{ tracking.url }}'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_key')->searchable(),
                TextColumn::make('channel')->badge(),
                TextColumn::make('subject')->placeholder('—')->limit(30),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationTemplates::route('/'),
            'create' => Pages\CreateNotificationTemplate::route('/create'),
            'edit' => Pages\EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}