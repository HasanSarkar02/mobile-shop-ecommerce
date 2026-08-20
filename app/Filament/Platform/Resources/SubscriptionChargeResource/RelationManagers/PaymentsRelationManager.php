<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\SubscriptionChargeResource\RelationManagers;

use App\Enums\SubscriptionPaymentStatus;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Filament\Platform\Support\PlatformMoney;
use App\Models\SubscriptionPayment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Linked Payments';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => PlatformMoney::format((int) $state)),
                TextColumn::make('reference')->searchable()->copyable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (SubscriptionPayment $record): string => self::statusLabel($record))
                    ->badge(),
                TextColumn::make('payment_method')->label('Method'),
                TextColumn::make('created_at')->label('Recorded')->dateTime(),
                TextColumn::make('received_at')->label('Verified at')->dateTime()->placeholder('—'),
                TextColumn::make('verifier.name')->label('Verified by')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View payment')
                    ->url(fn (SubscriptionPayment $record): string => SubscriptionPaymentResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }

    private static function statusLabel(SubscriptionPayment $record): string
    {
        $status = $record->getAttribute('status');

        return $status instanceof SubscriptionPaymentStatus ? $status->label() : (string) $status;
    }
}
