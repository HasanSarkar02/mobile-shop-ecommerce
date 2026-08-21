<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\OrderEventType;
use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Filament\Store\Resources\OrderResource\Pages;
use App\Models\CourierConnection;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\OrderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable(),
                TextColumn::make('customerDisplayName')->label('Customer')->state(fn (Order $record): string => $record->customerDisplayName()),
                TextColumn::make('status')->badge(),
                TextColumn::make('order_source')->badge(),
                TextColumn::make('grand_total')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
                TextColumn::make('placed_at')->dateTime(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->recordActions([ViewAction::make()])
            ->bulkActions([
                BulkAction::make('bulkSendToCourier')
                    ->label('Send to Courier (bulk)')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Select::make('courier_connection_id')
                            ->label('Courier')
                            ->options(fn (): array => CourierConnection::query()->where('is_active', true)->with('provider')->get()->mapWithKeys(fn ($c) => [$c->id => ($c->provider?->displayName() ?? 'Courier').' — '.($c->sandbox ? 'Sandbox' : 'Live')])->all())
                            ->required(),
                    ])
                    ->action(function (EloquentCollection $records, array $data): void {
                        $connection = CourierConnection::query()->whereKey($data['courier_connection_id'])->firstOrFail();
                        $provider = $connection->provider()->first() ?? CourierProvider::query()->findOrFail($connection->courier_provider_id);
                        $baseUrl = $provider->effectiveBaseUrl((bool) $connection->sandbox) ?: $connection->effectiveBaseUrl();
                        $driverClass = $provider->driver_class ?? config('couriers.drivers.'.$provider->code);
                        $driver = app($driverClass);

                        $orders = $records->load('fulfillments');
                        $result = $driver->createBulk($orders->all(), $connection->credentials ?? [], $baseUrl);

                        foreach ($result->items as $item) {
                            Notification::make()->title('Bulk result: '.($item['invoice'] ?? '').' — '.$item['status'])->send();
                        }
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Order')
                ->icon('heroicon-o-shopping-bag')
                ->columnSpanFull()
                ->columns(['sm' => 3, 'xl' => 6])
                ->schema([
                    TextEntry::make('order_number')->label('Order Number'),
                    TextEntry::make('invoice_number')->label('Invoice'),
                    TextEntry::make('placed_at')->dateTime()->label('Placed'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (OrderStatus $state): string => self::orderStatusColor($state))
                        ->label('Status'),
                    TextEntry::make('payment_status')
                        ->state(fn (Order $record): string => self::paymentStatusFor($record))
                        ->badge()
                        ->color(fn (string $state): string => self::paymentStatusColor($state))
                        ->label('Payment'),
                    TextEntry::make('fulfillment_status')
                        ->state(fn (Order $record): string => self::fulfillmentStatusFor($record))
                        ->badge()
                        ->color(fn (string $state): string => self::fulfillmentStatusColor($state))
                        ->label('Fulfillment'),
                    TextEntry::make('order_source')->badge()->label('Source'),
                    TextEntry::make('sales_channel')->label('Channel'),
                    TextEntry::make('reservation_expires_at')
                        ->dateTime()
                        ->hidden(fn (Order $record): bool => $record->reservation_expires_at === null)
                        ->label('Reservation Expires'),
                ]),

            Section::make('Order Summary')
                ->icon('heroicon-o-tag')
                ->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                ->headerActions([
                    self::applyOrderDiscountAction(),
                    self::editShippingAction(),
                ])
                ->schema([
                    TextEntry::make('subtotal')->money('BDT', divideBy: 100),
                    TextEntry::make('discount_total')
                        ->label('Discount')
                        ->money('BDT', divideBy: 100),
                    TextEntry::make('shipping_cost')
                        ->label('Shipping')
                        ->money('BDT', divideBy: 100),
                    TextEntry::make('tax_total')
                        ->label('Tax')
                        ->money('BDT', divideBy: 100),
                    TextEntry::make('grand_total')
                        ->money('BDT', divideBy: 100)
                        ->weight('bold')
                        ->size('lg')
                        ->columnSpanFull(),
                    TextEntry::make('amount_paid')
                        ->state(fn (Order $record): int => self::amountPaid($record))
                        ->money('BDT', divideBy: 100)
                        ->label('Amount Paid')
                        ->columnSpan(['xl' => 2]),
                    TextEntry::make('amount_due')
                        ->state(fn (Order $record): int => max(0, $record->grand_total - self::amountPaid($record)))
                        ->money('BDT', divideBy: 100)
                        ->label('Amount Due')
                        ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                        ->columnSpan(['xl' => 2]),
                    TextEntry::make('refund_required')
                        ->label('')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->weight('semibold')
                        ->state(fn (Order $record): string => 'Refund required — this cancelled order already has ৳'.number_format(self::amountPaid($record) / 100, 2).' paid. Issue the refund in the payment reconciliation phase.')
                        ->hidden(fn (Order $record): bool => $record->status !== OrderStatus::Cancelled || self::amountPaid($record) <= 0)
                        ->columnSpanFull(),
                ]),

            Section::make('Shipping Address')
                ->icon('heroicon-o-map-pin')
                ->columnSpanFull()
                ->headerActions([self::editShippingAddressAction()])
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                ->schema([
                    ...self::addressEntities('shipping_address_snapshot'),
                ]),

            Grid::make(['default' => 1, 'xl' => 1])
                ->columnSpanFull()
                ->schema([
                    Group::make()->columnSpanFull()->schema([
                        Section::make('Customer')
                            ->icon('heroicon-o-user-circle')
                            ->headerActions([
                                self::customerLinkAction(),
                                self::editContactAction(),
                            ])
                            ->columns(['sm' => 3])
                            ->schema([
                                TextEntry::make('customer.name')
                                    ->hidden(fn (Order $record): bool => $record->customer_id === null)
                                    ->label('Name')
                                    ->weight('semibold'),
                                TextEntry::make('customer.email')
                                    ->hidden(fn (Order $record): bool => $record->customer_id === null)
                                    ->label('Email')
                                    ->copyable(),
                                TextEntry::make('customer.phone')
                                    ->hidden(fn (Order $record): bool => $record->customer_id === null)
                                    ->label('Phone')
                                    ->copyable(),
                                TextEntry::make('_guest')
                                    ->hidden(fn (Order $record): bool => $record->customer_id !== null)
                                    ->state('Guest')
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-user')
                                    ->label('User Type'),
                                TextEntry::make('guest_name')
                                    ->hidden(fn (Order $record): bool => $record->customer_id !== null)
                                    ->label('Name'),
                                TextEntry::make('guest_email')
                                    ->hidden(fn (Order $record): bool => $record->customer_id !== null)
                                    ->label('Email')
                                    ->copyable(),
                                TextEntry::make('guest_phone')
                                    ->hidden(fn (Order $record): bool => $record->customer_id !== null)
                                    ->label('Phone')
                                    ->copyable(),
                            ]),

                        Section::make('Order Items')
                            ->icon('heroicon-o-squares-2x2')
                            ->headerActions([
                                self::addItemAction(),
                                self::changeItemQuantityAction(),
                                self::changeItemVariantAction(),
                                self::adjustItemPriceAction(),
                                self::removeItemAction(),
                            ])
                            ->schema([
                                ViewEntry::make('items')
                                    ->state(fn (Order $record) => $record->items)
                                    ->view('filament.store.resources.order-resource.order-items'),
                            ]),

                        Section::make('Payments')
                            ->icon('heroicon-o-banknotes')
                            ->description(fn (Order $record): string => self::paymentSummary($record))
                            ->headerActions([
                                self::recordPaymentAction(),
                                self::verifyManualPaymentAction(),
                                self::rejectManualPaymentAction(),
                            ])
                            ->schema([
                                RepeatableEntry::make('payments')
                                    ->hidden(fn (Order $record): bool => $record->payments()->count() === 0)
                                    ->schema([
                                        Grid::make(['default' => 1, 'md' => 5])->schema([
                                            TextEntry::make('paymentMethod.name')->label('Method'),
                                            TextEntry::make('status')
                                                ->badge()
                                                ->color(fn (OrderPaymentStatus $state): string => self::paymentStatusColor($state->value))
                                                ->label('Status'),
                                            TextEntry::make('amount')->money('BDT', divideBy: 100)->label('Amount'),
                                            TextEntry::make('transaction_reference')->label('Reference')->placeholder('—'),
                                            TextEntry::make('paid_at')->dateTime()->label('Paid At'),
                                        ]),
                                    ]),
                                TextEntry::make('_no_payments')
                                    ->label('')
                                    ->icon('heroicon-o-banknotes')
                                    ->state('No payments recorded yet.')
                                    ->color('gray')
                                    ->hidden(fn (Order $record): bool => $record->payments()->count() > 0),
                            ]),

                        Section::make('Fulfillment')
                            ->icon('heroicon-o-truck')
                            ->hidden(fn (Order $record): bool => $record->fulfillments()->count() === 0)
                            ->schema([
                                RepeatableEntry::make('fulfillments')
                                    ->hiddenLabel()
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])->schema([
                                            TextEntry::make('fulfillment_group')
                                                ->label('Group')
                                                ->badge()
                                                ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Stock'),
                                            TextEntry::make('status')
                                                ->badge()
                                                ->color(fn (OrderFulfillmentStatus $state): string => self::fulfillmentStatusColor($state->value))
                                                ->label('Status'),
                                            TextEntry::make('expected_available_at')
                                                ->label('ETA')
                                                ->dateTime('M j, Y')
                                                ->placeholder('—'),
                                            TextEntry::make('courier_name')->label('Courier')->placeholder('—'),
                                            TextEntry::make('tracking_number')->label('Tracking')->placeholder('—'),
                                            TextEntry::make('shipped_at')->dateTime()->label('Shipped At')->placeholder('—'),
                                            TextEntry::make('delivered_at')->dateTime()->label('Delivered At')->placeholder('—'),
                                        ]),
                                    ]),
                            ]),

                        Section::make('Inventory Movements')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                ViewEntry::make('stock_movements')
                                    ->state(fn (Order $record) => $record->stockMovements()->with('variant')->orderByDesc('created_at')->get())
                                    ->view('filament.store.resources.order-resource.order-inventory'),
                            ]),

                        Section::make('Timeline')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                ViewEntry::make('events')
                                    ->state(fn (Order $record) => $record->events()->with('actor')->get())
                                    ->view('filament.store.resources.order-resource.order-timeline'),
                            ]),
                    ]),

                    Group::make()->columnSpanFull()->schema([
                        Section::make('Billing Address')
                            ->icon('heroicon-o-credit-card')
                            ->hidden(fn (Order $record): bool => $record->billing_address_snapshot === null)
                            ->headerActions([self::editBillingAddressAction()])
                            ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                            ->schema([
                                ...self::addressEntities('billing_address_snapshot'),
                            ]),

                        Section::make('Internal Note')
                            ->icon('heroicon-o-chat-bubble-bottom-center-text')
                            ->hidden(fn (Order $record): bool => blank($record->internal_note))
                            ->schema([
                                TextEntry::make('internal_note')->prose(),
                            ]),
                    ]),
                ]),
        ]);
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->order_number ?? static::getTitle();
    }

    public static function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label('Record Payment')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->form([
                Select::make('payment_method_id')
                    ->options(fn () => PaymentMethod::query()->pluck('name', 'id'))
                    ->required(),
                TextInput::make('amount')
                    ->label('Amount (BDT)')
                    ->numeric()
                    ->required(),
                Select::make('status')
                    ->options(collect(OrderPaymentStatus::cases())
                        ->reject(fn (OrderPaymentStatus $case): bool => $case === OrderPaymentStatus::Refunded)
                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->helperText('Refunds are not yet supported and cannot be recorded from this screen.')
                    ->required(),
                TextInput::make('transaction_reference'),
            ])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->recordPayment(
                    $record,
                    PaymentMethod::find($data['payment_method_id']),
                    (int) round($data['amount'] * 100),
                    OrderPaymentStatus::from($data['status']),
                    $data['transaction_reference'] ?? null,
                );
            });
    }

    public static function verifyManualPaymentAction(): Action
    {
        return Action::make('verifyManualPayment')
            ->label('Verify Manual Payment')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Order $record): bool => $record->payments()->where('status', OrderPaymentStatus::Pending)->exists())
            ->form([
                Select::make('order_payment_id')
                    ->label('Pending Payment')
                    ->options(fn (Order $record) => $record->payments()->where('status', OrderPaymentStatus::Pending)->with('paymentMethod')->get()->mapWithKeys(fn (OrderPayment $p) => [$p->id => ($p->paymentMethod?->displayName() ?? 'Payment').' — ৳'.number_format($p->amount / 100, 2).' — '.$p->transaction_reference]))
                    ->required(),
            ])
            ->action(function (Order $record, array $data): void {
                $payment = OrderPayment::query()->whereKey($data['order_payment_id'])->where('order_id', $record->id)->firstOrFail();

                if ($payment->status !== OrderPaymentStatus::Pending) {
                    return;
                }

                // Verify that this is a manual verification flow (shop-owned, not COD).
                $method = $payment->paymentMethod;
                if ($method && $method->type->isCod()) {
                    return;
                }

                $payment->forceFill([
                    'status' => OrderPaymentStatus::Paid,
                    'paid_at' => now(),
                ])->save();

                $record->events()->create([
                    'tenant_id' => $record->tenant_id,
                    'type' => OrderEventType::PaymentRecorded,
                    'description' => 'Manual payment verified — '.$payment->transaction_reference.' marked as Paid.',
                    'metadata' => ['order_payment_id' => $payment->id, 'transaction_reference' => $payment->transaction_reference],
                    'created_by' => auth()->id(),
                ]);

                // Auto-confirm order if fully paid, mirroring OrderService::recordPayment behaviour.
                $orders = app(OrderService::class);
                $paidAlready = $orders->amountPaid($record);

                if ($record->status === OrderStatus::Pending && $paidAlready >= (int) $record->grand_total) {
                    $orders->updateStatus($record, OrderStatus::Confirmed, 'Confirmed — manual payment verified.');
                }
            });
    }

    public static function rejectManualPaymentAction(): Action
    {
        return Action::make('rejectManualPayment')
            ->label('Reject Manual Payment')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->payments()->where('status', OrderPaymentStatus::Pending)->exists())
            ->requiresConfirmation()
            ->form([
                Select::make('order_payment_id')
                    ->label('Pending Payment')
                    ->options(fn (Order $record) => $record->payments()->where('status', OrderPaymentStatus::Pending)->with('paymentMethod')->get()->mapWithKeys(fn (OrderPayment $p) => [$p->id => ($p->paymentMethod?->displayName() ?? 'Payment').' — ৳'.number_format($p->amount / 100, 2).' — '.$p->transaction_reference]))
                    ->required(),
                Textarea::make('reason')->label('Reason')->required(),
            ])
            ->action(function (Order $record, array $data): void {
                $payment = OrderPayment::query()->whereKey($data['order_payment_id'])->where('order_id', $record->id)->firstOrFail();

                if ($payment->status !== OrderPaymentStatus::Pending) {
                    return;
                }

                $payment->forceFill([
                    'status' => OrderPaymentStatus::Failed,
                ])->save();

                $record->events()->create([
                    'tenant_id' => $record->tenant_id,
                    'type' => OrderEventType::PaymentRecorded,
                    'description' => 'Manual payment rejected — '.$payment->transaction_reference.': '.$data['reason'],
                    'metadata' => ['order_payment_id' => $payment->id, 'reason' => $data['reason']],
                    'created_by' => auth()->id(),
                ]);
            });
    }

    private static function customerLinkAction(): Action
    {
        return Action::make('viewCustomer')
            ->label('View Customer')
            ->icon('heroicon-o-user-circle')
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->customer !== null)
            ->url(fn (Order $record): ?string => $record->customer?->exists
                ? CustomerResource::getUrl('edit', ['record' => $record->customer])
                : null);
    }

    private static function editContactAction(): Action
    {
        return Action::make('editContact')
            ->label('Edit Contact')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->customer_id === null)
            ->form([
                TextInput::make('guest_name')->label('Name')->required(),
                TextInput::make('guest_email')->label('Email')->email(),
                TextInput::make('guest_phone')->label('Phone')->tel(),
            ])
            ->fillForm(fn (Order $record): array => [
                'guest_name' => $record->guest_name,
                'guest_email' => $record->guest_email,
                'guest_phone' => $record->guest_phone,
            ])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->updateOrderContact(
                    $record,
                    $data['guest_name'],
                    $data['guest_email'] ?? null,
                    $data['guest_phone'] ?? null,
                );
            });
    }

    private static function editShippingAddressAction(): Action
    {
        return Action::make('editShippingAddress')
            ->label('Edit Address')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->form([
                TextInput::make('recipient_name')->label('Recipient Name')->required(),
                TextInput::make('phone')->label('Phone')->tel(),
                TextInput::make('address_line_1')->label('Address Line 1')->required(),
                TextInput::make('address_line_2')->label('Address Line 2'),
                TextInput::make('city')->label('City')->required(),
                TextInput::make('area')->label('Area'),
                TextInput::make('postal_code')->label('Postal Code'),
                TextInput::make('country')->label('Country'),
            ])
            ->fillForm(fn (Order $record): array => $record->shipping_address_snapshot ?? [])
            ->action(function (Order $record, array $data): void {
                $snapshot = $record->shipping_address_snapshot ?? [];

                app(OrderService::class)->updateOrderShippingAddress($record, array_merge($snapshot, array_filter($data, fn ($value) => $value !== null)));
            });
    }

    private static function editBillingAddressAction(): Action
    {
        return Action::make('editBillingAddress')
            ->label('Edit Address')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->form([
                TextInput::make('recipient_name')->label('Recipient Name')->required(),
                TextInput::make('phone')->label('Phone')->tel(),
                TextInput::make('address_line_1')->label('Address Line 1')->required(),
                TextInput::make('address_line_2')->label('Address Line 2'),
                TextInput::make('city')->label('City')->required(),
                TextInput::make('area')->label('Area'),
                TextInput::make('postal_code')->label('Postal Code'),
                TextInput::make('country')->label('Country'),
            ])
            ->fillForm(fn (Order $record): array => $record->billing_address_snapshot ?? [])
            ->action(function (Order $record, array $data): void {
                $snapshot = $record->billing_address_snapshot ?? [];

                app(OrderService::class)->updateOrderBillingAddress($record, array_merge($snapshot, array_filter($data, fn ($value) => $value !== null)));
            });
    }

    private static function addItemAction(): Action
    {
        return Action::make('addItem')
            ->label('Add Item')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->form([
                Select::make('product_variant_id')
                    ->label('Variant')
                    ->options(self::variantOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
            ])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->addItem(
                    $record,
                    ProductVariant::findOrFail($data['product_variant_id']),
                    (int) $data['quantity'],
                );
            });
    }

    private static function changeItemQuantityAction(): Action
    {
        return Action::make('changeItemQuantity')
            ->label('Change Quantity')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->form([
                Select::make('order_item_id')
                    ->label('Item')
                    ->options(fn (Order $record): array => self::itemOptions($record))
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
            ])
            ->fillForm(fn (Order $record): array => ['order_item_id' => $record->items->first()?->id])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->updateItemQuantity(
                    $record,
                    $record->items()->findOrFail($data['order_item_id']),
                    (int) $data['quantity'],
                );
            });
    }

    private static function changeItemVariantAction(): Action
    {
        return Action::make('changeItemVariant')
            ->label('Change Variant')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->form([
                Select::make('order_item_id')
                    ->label('Item')
                    ->options(fn (Order $record): array => self::itemOptions($record))
                    ->required(),
                Select::make('product_variant_id')
                    ->label('New Variant')
                    ->options(self::variantOptions())
                    ->searchable()
                    ->required(),
            ])
            ->fillForm(fn (Order $record): array => ['order_item_id' => $record->items->first()?->id])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->changeItemVariant(
                    $record,
                    $record->items()->findOrFail($data['order_item_id']),
                    ProductVariant::findOrFail($data['product_variant_id']),
                );
            });
    }

    private static function adjustItemPriceAction(): Action
    {
        return Action::make('adjustItemPrice')
            ->label('Adjust Price')
            ->icon('heroicon-o-currency-dollar')
            ->color('warning')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->form([
                Select::make('order_item_id')
                    ->label('Item')
                    ->options(fn (Order $record): array => self::itemOptions($record))
                    ->required(),
                TextInput::make('unit_price')
                    ->label('New Unit Price (BDT)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(2),
            ])
            ->fillForm(fn (Order $record): array => [
                'order_item_id' => $record->items->first()?->id,
                'unit_price' => $record->items->first() ? $record->items->first()->unit_price / 100 : null,
            ])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->adjustItemUnitPrice(
                    $record,
                    $record->items()->findOrFail($data['order_item_id']),
                    (int) round($data['unit_price'] * 100),
                    $data['reason'],
                );
            });
    }

    private static function removeItemAction(): Action
    {
        return Action::make('removeItem')
            ->label('Remove Item')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->requiresConfirmation()
            ->form([
                Select::make('order_item_id')
                    ->label('Item')
                    ->options(fn (Order $record): array => self::itemOptions($record))
                    ->required(),
                TextInput::make('reason')->label('Reason (optional)'),
            ])
            ->fillForm(fn (Order $record): array => ['order_item_id' => $record->items->first()?->id])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->removeItem(
                    $record,
                    $record->items()->findOrFail($data['order_item_id']),
                    $data['reason'] ?? null,
                );
            });
    }

    private static function applyOrderDiscountAction(): Action
    {
        return Action::make('applyOrderDiscount')
            ->label('Apply Discount')
            ->icon('heroicon-o-ticket')
            ->color('primary')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->form([
                TextInput::make('discount')
                    ->label('Discount Amount (BDT)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(2),
            ])
            ->fillForm(fn (Order $record): array => ['discount' => $record->discount_total / 100])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->applyOrderDiscount($record, (int) round($data['discount'] * 100), $data['reason']);
            });
    }

    private static function editShippingAction(): Action
    {
        return Action::make('editShipping')
            ->label('Edit Shipping')
            ->icon('heroicon-o-truck')
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->form([
                Select::make('shipping_method_id')
                    ->label('Shipping Method')
                    ->options(fn () => ShippingMethod::query()->where('is_active', true)->pluck('name', 'id'))
                    ->nullable(),
                TextInput::make('shipping_cost')
                    ->label('Shipping Cost (BDT)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ])
            ->fillForm(fn (Order $record): array => [
                'shipping_method_id' => $record->shipping_method_id,
                'shipping_cost' => $record->shipping_cost / 100,
            ])
            ->action(function (Order $record, array $data): void {
                app(OrderService::class)->updateShipping(
                    $record,
                    (int) round($data['shipping_cost'] * 100),
                    $data['shipping_method_id'] ?? null,
                    'Updated from the order screen',
                );
            });
    }

    private static function variantOptions(): array
    {
        return ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->sku.' — '.($variant->product->name ?? '')])
            ->all();
    }

    private static function itemOptions(Order $record): array
    {
        return $record->items
            ->mapWithKeys(fn ($item): array => [$item->id => $item->variant_sku_snapshot.' — ৳'.number_format($item->unit_price / 100, 2).' × '.$item->quantity])
            ->all();
    }

    private static function addressEntities(string $column): array
    {
        return [
            TextEntry::make($column.'.recipient_name')
                ->label('Recipient')
                ->placeholder('—')
                ->columnSpan(['default' => 1, 'sm' => 1, 'xl' => 2]),
            TextEntry::make($column.'.phone')
                ->label('Phone')
                ->placeholder('—')
                ->columnSpan(['default' => 1, 'sm' => 1, 'xl' => 2])
                ->hidden(fn (Order $record): bool => blank(data_get($record, $column.'.phone'))),
            TextEntry::make($column.'.address_line_1')->label('Address Line 1')->placeholder('—')->columnSpanFull(),
            TextEntry::make($column.'.address_line_2')
                ->label('Address Line 2')
                ->placeholder('—')
                ->columnSpanFull()
                ->hidden(fn (Order $record): bool => blank(data_get($record, $column.'.address_line_2'))),
            TextEntry::make($column.'.city')
                ->label('City')
                ->placeholder('—')
                ->hidden(fn (Order $record): bool => blank(data_get($record, $column.'.city'))),
            TextEntry::make($column.'.area')
                ->label('Area')
                ->placeholder('—')
                ->hidden(fn (Order $record): bool => blank(data_get($record, $column.'.area'))),
            TextEntry::make($column.'.postal_code')
                ->label('Postal Code')
                ->placeholder('—')
                ->hidden(fn (Order $record): bool => blank(data_get($record, $column.'.postal_code'))),
            TextEntry::make($column.'.country')
                ->label('Country')
                ->placeholder('—')
                ->hidden(fn (Order $record): bool => blank(data_get($record, $column.'.country'))),
        ];
    }

    private static function paymentSummary(Order $record): string
    {
        $paid = self::amountPaid($record);
        $due = max(0, $record->grand_total - $paid);

        if ($paid >= $record->grand_total && $record->grand_total > 0) {
            return 'Fully paid — ৳'.number_format($paid / 100, 2).' collected.';
        }

        return '৳'.number_format($paid / 100, 2).' paid · ৳'.number_format($due / 100, 2).' due.';
    }

    private static function amountPaid(Order $record): int
    {
        return (int) $record->payments()->where('status', OrderPaymentStatus::Paid)->sum('amount');
    }

    private static function paymentStatusFor(Order $record): string
    {
        if (self::amountPaid($record) >= $record->grand_total && $record->grand_total > 0) {
            return 'paid';
        }

        return $record->payments()->exists() ? 'partial' : 'none';
    }

    private static function fulfillmentStatusFor(Order $record): string
    {
        return $record->fulfillments()->latest()?->first()?->status?->value ?? 'none';
    }

    private static function orderStatusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'warning',
            OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped => 'info',
            OrderStatus::Delivered => 'success',
            OrderStatus::Cancelled => 'danger',
            OrderStatus::Refunded => 'gray',
        };
    }

    private static function paymentStatusColor(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'partial', 'pending' => 'warning',
            'failed' => 'danger',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    private static function fulfillmentStatusColor(string $status): string
    {
        return match ($status) {
            'delivered' => 'success',
            'failed' => 'danger',
            'pending' => 'warning',
            default => 'info',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
