<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Filament\Store\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\OrderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
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
            ->recordActions([ViewAction::make()]);
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

            Grid::make(['default' => 1, 'xl' => 3])
                ->columnSpanFull()
                ->schema([
                Group::make()->columnSpan(2)->schema([
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
                        ->columns(['sm' => 2, 'xl' => 3])
                        ->schema([
                            TextEntry::make('shipping_method')
                                ->state(fn (Order $record): ?string => $record->fulfillments()->latest()->first()?->shippingMethod?->name)
                                ->label('Shipping Method'),
                            TextEntry::make('fulfillment_status')
                                ->state(fn (Order $record): ?string => self::fulfillmentStatusFor($record))
                                ->badge()
                                ->color(fn (string $state): string => self::fulfillmentStatusColor($state))
                                ->label('Status'),
                            TextEntry::make('fulfillment_location')
                                ->state(fn (Order $record): ?string => $record->fulfillments()->latest()->first()?->location?->name)
                                ->label('Location')
                                ->placeholder('—'),
                            TextEntry::make('fulfillment_courier_name')
                                ->state(fn (Order $record): ?string => $record->fulfillments()->latest()->first()?->courier_name)
                                ->label('Courier')
                                ->placeholder('—'),
                            TextEntry::make('fulfillment_tracking_number')
                                ->state(fn (Order $record): ?string => $record->fulfillments()->latest()->first()?->tracking_number)
                                ->label('Tracking Number')
                                ->placeholder('—'),
                            TextEntry::make('fulfillment_shipped_at')
                                ->state(fn (Order $record) => $record->fulfillments()->latest()->first()?->shipped_at)
                                ->dateTime()
                                ->placeholder('—')
                                ->label('Shipped At'),
                            TextEntry::make('fulfillment_delivered_at')
                                ->state(fn (Order $record) => $record->fulfillments()->latest()->first()?->delivered_at)
                                ->dateTime()
                                ->placeholder('—')
                                ->label('Delivered At'),
                        ]),

                    Section::make('Timeline')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            ViewEntry::make('events')
                                ->state(fn (Order $record) => $record->events()->get()->reverse())
                                ->view('filament.store.resources.order-resource.order-timeline'),
                        ]),
                ]),

                Group::make()->columnSpan(1)->schema([
                    Section::make('Order Summary')
                        ->icon('heroicon-o-tag')
                        ->schema([
                            TextEntry::make('subtotal')->money('BDT', divideBy: 100),
                            TextEntry::make('discount_total')->money('BDT', divideBy: 100),
                            TextEntry::make('shipping_cost')->money('BDT', divideBy: 100),
                            TextEntry::make('tax_total')->money('BDT', divideBy: 100),
                            TextEntry::make('grand_total')
                                ->money('BDT', divideBy: 100)
                                ->weight('bold')
                                ->size('lg')
                                ->columnSpanFull(),
                            TextEntry::make('amount_paid')
                                ->state(fn (Order $record): int => self::amountPaid($record))
                                ->money('BDT', divideBy: 100)
                                ->label('Amount Paid'),
                            TextEntry::make('amount_due')
                                ->state(fn (Order $record): int => max(0, $record->grand_total - self::amountPaid($record)))
                                ->money('BDT', divideBy: 100)
                                ->label('Amount Due')
                                ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                        ]),

                    Section::make('Shipping Address')
                        ->icon('heroicon-o-map-pin')
                        ->headerActions([self::editShippingAddressAction()])
                        ->columns(['default' => 1, 'sm' => 2])
                        ->schema([
                            ...self::addressEntities('shipping_address_snapshot'),
                        ]),

                    Section::make('Billing Address')
                        ->icon('heroicon-o-credit-card')
                        ->hidden(fn (Order $record): bool => $record->billing_address_snapshot === null)
                        ->columns(['default' => 1, 'sm' => 2])
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
                    ->options(collect(OrderPaymentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
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

    private static function addressEntities(string $column): array
    {
        return [
            TextEntry::make($column.'.recipient_name')->label('Recipient')->placeholder('—')->columnSpanFull(),
            TextEntry::make($column.'.phone')->label('Phone')->placeholder('—')->columnSpanFull(),
            TextEntry::make($column.'.address_line_1')->label('Address Line 1')->placeholder('—')->columnSpanFull(),
            TextEntry::make($column.'.address_line_2')->label('Address Line 2')->placeholder('—')->columnSpanFull(),
            TextEntry::make($column.'.city')->label('City')->placeholder('—'),
            TextEntry::make($column.'.area')->label('Area')->placeholder('—'),
            TextEntry::make($column.'.postal_code')->label('Postal Code')->placeholder('—'),
            TextEntry::make($column.'.country')->label('Country')->placeholder('—'),
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
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
