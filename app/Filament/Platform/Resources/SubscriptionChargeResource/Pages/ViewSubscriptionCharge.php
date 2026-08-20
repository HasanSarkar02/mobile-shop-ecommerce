<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\SubscriptionChargeResource\Pages;

use App\Filament\Platform\Resources\SubscriptionChargeResource;
use App\Filament\Platform\Resources\SubscriptionChargeResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Models\SubscriptionCharge;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriptionCharge extends ViewRecord
{
    protected static string $resource = SubscriptionChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordPayment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->url(fn (SubscriptionCharge $record): string => SubscriptionPaymentResource::getUrl('create', ['charge' => $record->getKey()]))
                ->visible(fn (SubscriptionCharge $record): bool => $record->outstandingAmount() > 0),
        ];
    }

    public function getRelationManagers(): array
    {
        return [PaymentsRelationManager::class];
    }
}
