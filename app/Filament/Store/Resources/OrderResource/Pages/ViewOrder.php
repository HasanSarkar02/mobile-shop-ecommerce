<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OrderResource\Pages;

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Filament\Store\Resources\OrderResource;
use App\Models\PaymentMethod;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.store.resources.order-resource.pages.view-order';

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
                ->action(function (array $data): void {
                    app(OrderService::class)->updateStatus($this->record, OrderStatus::from($data['status']), $data['note'] ?? null);
                }),

            Action::make('recordPayment')
                ->label('Record Payment')
                ->schema([
                    Select::make('payment_method_id')->options(fn () => PaymentMethod::query()->pluck('name', 'id'))->required(),
                    TextInput::make('amount')->label('Amount (BDT)')->numeric()->required(),
                    Select::make('status')
                        ->options(collect(OrderPaymentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                        ->required(),
                    TextInput::make('transaction_reference'),
                ])
                ->action(function (array $data): void {
                    app(OrderService::class)->recordPayment(
                        $this->record,
                        PaymentMethod::find($data['payment_method_id']),
                        (int) round($data['amount'] * 100),
                        OrderPaymentStatus::from($data['status']),
                        $data['transaction_reference'] ?? null,
                    );
                }),

            Action::make('updateFulfillment')
                ->label('Update Fulfillment')
                ->schema([
                    Select::make('status')
                        ->options(collect(OrderFulfillmentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                        ->required(),
                    TextInput::make('tracking_number'),
                    TextInput::make('courier_name'),
                ])
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
        ];
    }
}