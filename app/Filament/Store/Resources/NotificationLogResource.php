<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\NotificationStatus;
use App\Filament\Store\Resources\NotificationLogResource\Pages;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Notifications';

    protected static ?string $navigationLabel = 'Delivery Log';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->label('Queued At'),
                TextColumn::make('event_key'),
                TextColumn::make('channel')->badge(),
                TextColumn::make('recipient_address'),
                TextColumn::make('status')->badge(),
                TextColumn::make('attempts'),
                TextColumn::make('error_message')->limit(30)->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (NotificationLog $record): bool => $record->status === NotificationStatus::Failed)
                    ->action(function (NotificationLog $record): void {
                        $record->update(['status' => NotificationStatus::Queued]);
                        SendNotificationJob::dispatch($record->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNotificationLogs::route('/')];
    }
}
