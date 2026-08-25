<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OrderResource\Pages;

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Store\Resources\OrderResource;
use App\Models\CourierConnection;
use App\Models\OrderFulfillment;
use App\Services\OrderService;
use App\Services\Shipping\CodAmountResolver;
use App\Services\Shipping\CourierService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;

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

            Action::make('recordCodCollection')
                ->label('Record COD Collection')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                // Deliberately manual. A courier reporting "delivered" is not the
                // same as the courier remitting the cash, and partial collection is
                // common, so the money is only booked once staff confirm the amount.
                ->visible(fn (): bool => $this->record->paymentMethod?->isCod() === true
                    && (int) $this->record->grand_total > app(OrderService::class)->amountPaid($this->record)
                    && $this->record->fulfillments()->exists())
                ->schema([
                    Select::make('fulfillment_id')
                        ->label('Consignment')
                        ->options(fn (): array => $this->codConsignmentOptions())
                        ->default(fn (): ?int => array_key_first($this->codConsignmentOptions()))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('amount', $this->codDueInTaka($state === null ? null : (int) $state));
                        }),
                    TextInput::make('amount')
                        ->label('Cash Collected (BDT)')
                        ->numeric()
                        ->required()
                        ->step(0.01)
                        ->default(fn (): ?string => $this->codDueInTaka(array_key_first($this->codConsignmentOptions())))
                        ->helperText('Prefilled with the amount due for this consignment. Lower it if the courier collected less than the full amount.'),
                ])
                ->requiresConfirmation()
                ->modalDescription('Records cash the courier collected for this consignment. Only book it once the money is actually in hand.')
                ->action(function (array $data): void {
                    $fulfillment = $this->record->fulfillments()->whereKey($data['fulfillment_id'])->first();

                    if (! $fulfillment) {
                        Notification::make()->title('Consignment not found')->danger()->send();

                        return;
                    }

                    try {
                        $payment = app(OrderService::class)->recordCodCollection($fulfillment, (int) round((float) $data['amount'] * 100));

                        if ($payment === null) {
                            Notification::make()
                                ->title('Already recorded')
                                ->body('Cash for this consignment has already been booked.')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()->title('COD collection recorded')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Could not record collection')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('refundOrder')
                ->label('Refund')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => app(OrderService::class)->amountPaid($this->record) > app(OrderService::class)->amountRefunded($this->record))
                ->schema([
                    TextInput::make('amount')->label('Refund Amount (BDT)')->numeric()->required()->helperText(fn (): string => 'Refundable: '.money((int) (app(OrderService::class)->amountPaid($this->record) - app(OrderService::class)->amountRefunded($this->record)))),
                    Textarea::make('reason')->label('Reason')->required()->rows(2),
                    TextInput::make('reference')->label('Reference (optional)'),
                    Toggle::make('restock')
                        ->label('Return goods to stock')
                        // Only offered where it is actually sound: the goods left and
                        // physically came back. A cancelled order was already
                        // restocked by the cancellation, and a partial amount cannot
                        // say which items returned.
                        ->visible(fn (): bool => $this->record->status === OrderStatus::Delivered)
                        ->helperText('Only for a full refund of a delivered order, once the items are physically back.')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    try {
                        app(OrderService::class)->refund(
                            $this->record,
                            (int) round((float) $data['amount'] * 100),
                            $data['reason'],
                            null,
                            $data['reference'] ?? null,
                            (bool) ($data['restock'] ?? false),
                        );
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

    /**
     * Consignments that still have cash to collect, labelled with the amount due.
     *
     * Delivered and failed consignments are excluded because CodAmountResolver
     * allocates them nothing — offering them here would only ever prefill zero.
     *
     * @return array<int, string>
     */
    private function codConsignmentOptions(): array
    {
        $resolver = app(CodAmountResolver::class);

        return $this->record->fulfillments()
            ->whereNotIn('status', [OrderFulfillmentStatus::Delivered->value, OrderFulfillmentStatus::Failed->value])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (OrderFulfillment $fulfillment): array => [
                $fulfillment->id => ucfirst($fulfillment->fulfillment_group ?? 'stock')
                    .' — '.$fulfillment->status->label()
                    .' ('.money((int) $resolver->minorUnitsFor($this->record, $fulfillment)).' due)',
            ])
            ->all();
    }

    /**
     * The consignment's due as a decimal string for the amount field. Kept as a
     * string so the prefill is not re-rounded by float formatting.
     */
    private function codDueInTaka(?int $fulfillmentId): ?string
    {
        if ($fulfillmentId === null) {
            return null;
        }

        $fulfillment = $this->record->fulfillments()->whereKey($fulfillmentId)->first();

        if (! $fulfillment) {
            return null;
        }

        return number_format(app(CodAmountResolver::class)->minorUnitsFor($this->record, $fulfillment) / 100, 2, '.', '');
    }
}
