<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OrderResource\Pages;

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Store\Resources\OrderResource;
use App\Models\CourierConnection;
use App\Services\OrderService;
use App\Services\Shipping\CourierService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printReceipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('store.orders.receipt', ['order' => $this->record]))
                ->openUrlInNewTab(),

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

            Action::make('refundOrder')
                ->label('Refund')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => app(OrderService::class)->amountPaid($this->record) > app(OrderService::class)->amountRefunded($this->record))
                ->schema([
                    TextInput::make('amount')->label('Refund Amount (BDT)')->numeric()->required()->helperText(fn (): string => 'Refundable: ৳'.number_format((app(OrderService::class)->amountPaid($this->record) - app(OrderService::class)->amountRefunded($this->record)) / 100, 2)),
                    Textarea::make('reason')->label('Reason')->required()->rows(2),
                    TextInput::make('reference')->label('Reference (optional)'),
                ])
                ->action(function (array $data): void {
                    try {
                        app(OrderService::class)->refund($this->record, (int) round((float) $data['amount'] * 100), $data['reason'], null, $data['reference'] ?? null);
                        Notification::make()->title('Refund recorded')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Refund failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('updateFulfillment')
                ->label('Update Fulfillment')
                ->schema([
                    Select::make('fulfillment_id')
                        ->label('Fulfillment')
                        ->options(fn (): array => $this->record->fulfillments()->get()->mapWithKeys(fn ($f) => [$f->id => ucfirst($f->fulfillment_group ?? 'stock').' — '.$f->status->label().($f->expected_available_at ? ' (ETA '.$f->expected_available_at->format('M j, Y').')' : '')])->all())
                        ->required()
                        ->visible(fn (): bool => $this->record->fulfillments()->count() > 1),
                    Select::make('status')
                        ->options(collect(OrderFulfillmentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                        ->required(),
                    TextInput::make('tracking_number'),
                    TextInput::make('courier_name'),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $fulfillment = isset($data['fulfillment_id'])
                        ? $this->record->fulfillments()->whereKey($data['fulfillment_id'])->first()
                        : $this->record->fulfillments()->latest()->first();

                    if ($fulfillment) {
                        app(OrderService::class)->updateFulfillment(
                            $fulfillment,
                            OrderFulfillmentStatus::from($data['status']),
                            $data['tracking_number'] ?? null,
                            $data['courier_name'] ?? null,
                        );
                    }
                }),

            Action::make('sendToCourier')
                ->label('Send to Courier')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->schema([
                    Select::make('courier_connection_id')
                        ->label('Courier')
                        ->options(fn (): array => CourierConnection::query()->where('is_active', true)->with('provider')->get()->mapWithKeys(fn ($c) => [$c->id => ($c->provider?->displayName() ?? 'Courier').' — '.($c->sandbox ? 'Sandbox' : 'Live')])->all())
                        ->required()
                        ->helperText(fn (): string => CourierConnection::query()->where('is_active', true)->exists() ? '' : 'No active courier connection. Configure one in Shipping → Courier Connections.'),
                    Select::make('fulfillment_id')
                        ->label('Fulfillment')
                        ->options(fn (): array => $this->record->fulfillments()->get()->mapWithKeys(fn ($f) => [$f->id => ucfirst($f->fulfillment_group ?? 'stock').' — '.$f->status->label().($f->tracking_number ? ' ('.$f->tracking_number.')' : '')])->all())
                        ->required()
                        ->visible(fn (): bool => $this->record->fulfillments()->count() > 1),
                ])
                ->action(function (array $data): void {
                    $fulfillment = isset($data['fulfillment_id'])
                        ? $this->record->fulfillments()->whereKey($data['fulfillment_id'])->first()
                        : $this->record->fulfillments()->latest()->first();

                    $connection = CourierConnection::query()->whereKey($data['courier_connection_id'])->firstOrFail();

                    if (! $fulfillment) {
                        Notification::make()->title('No fulfillment to ship')->danger()->send();

                        return;
                    }

                    try {
                        $result = app(CourierService::class)->sendFulfillment($this->record, $fulfillment, $connection);
                        Notification::make()->title('Courier shipment created')->body('Tracking: '.$result->trackingCode.' ('.$result->status.')')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Courier shipment failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('syncCourierStatus')
                ->label('Sync Courier Status')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->record->fulfillments()->whereNotNull('tracking_number')->exists())
                ->schema([
                    Select::make('fulfillment_id')
                        ->label('Fulfillment')
                        ->options(fn (): array => $this->record->fulfillments()->whereNotNull('tracking_number')->get()->mapWithKeys(fn ($f) => [$f->id => $f->tracking_number.' — '.($f->fulfillment_group ?? 'stock')])->all())
                        ->required(),
                    Select::make('courier_connection_id')
                        ->label('Courier')
                        ->options(fn (): array => CourierConnection::query()->where('is_active', true)->with('provider')->get()->mapWithKeys(fn ($c) => [$c->id => ($c->provider?->displayName() ?? 'Courier')])->all())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $fulfillment = $this->record->fulfillments()->whereKey($data['fulfillment_id'])->firstOrFail();
                    $connection = CourierConnection::query()->whereKey($data['courier_connection_id'])->firstOrFail();

                    try {
                        $status = app(CourierService::class)->syncStatus($fulfillment, $connection);
                        Notification::make()->title('Courier status synced')->body('Status: '.$status->status.' ('.$status->rawStatus.')')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
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
                ->schema([
                    Textarea::make('reason')->label('Reason for cancellation')->required()->rows(2),
                ])
                ->modalDescription('This will cancel the order, release or restock any reserved/committed inventory, and flag any refund that becomes due. This action cannot be undone.')
                ->visible(fn (): bool => in_array(OrderStatus::Cancelled, $this->record->status->allowedNextStatuses(), true))
                ->action(function (array $data): void {
                    app(OrderService::class)->cancelOrder($this->record, $data['reason']);
                }),
        ];
    }
}
