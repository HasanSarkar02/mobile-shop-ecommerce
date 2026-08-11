<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OrderResource\Pages;

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Store\Resources\OrderResource;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateStatus')
                ->label('Update Status')
                ->schema([
                    Select::make('status')
                        ->options(collect($this->record->status->allowedNextStatuses())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                        ->required(),
                    Textarea::make('note')->rows(2),
                ])
                ->visible(fn (): bool => $this->record->status->allowedNextStatuses() !== [])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    app(OrderService::class)->updateStatus($this->record, OrderStatus::from($data['status']), $data['note'] ?? null);
                }),

            OrderResource::recordPaymentAction(),

            Action::make('updateFulfillment')
                ->label('Update Fulfillment')
                ->schema([
                    Select::make('status')
                        ->options(collect(OrderFulfillmentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                        ->required(),
                    TextInput::make('tracking_number'),
                    TextInput::make('courier_name'),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $fulfillment = $this->record->fulfillments()->latest()->first();

                    if ($fulfillment) {
                        app(OrderService::class)->updateFulfillment(
                            $fulfillment,
                            OrderFulfillmentStatus::from($data['status']),
                            $data['tracking_number'] ?? null,
                            $data['courier_name'] ?? null,
                        );
                    }
                }),

            Action::make('addInternalNote')
                ->label('Add Internal Note')
                ->schema([Textarea::make('note')->required()->rows(3)])
                ->action(function (array $data): void {
                    app(OrderService::class)->addInternalNote($this->record, $data['note']);
                }),

            Action::make('cancelOrder')
                ->label('Cancel Order')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalDescription('This will cancel the order, release any reserved stock, and mark it as cancelled. This action cannot be undone.')
                ->visible(fn (): bool => in_array(OrderStatus::Cancelled, $this->record->status->allowedNextStatuses(), true))
                ->action(function (): void {
                    app(OrderService::class)->updateStatus($this->record, OrderStatus::Cancelled, 'Order cancelled by staff.');
                }),
        ];
    }
}
