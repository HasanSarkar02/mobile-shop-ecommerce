<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\PaymentMethodType;
use App\Filament\Store\Concerns\RestrictsToOwner;
use App\Filament\Store\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    use RestrictsToOwner;

    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Code')
                ->helperText('Unique per shop, e.g. cod, bkash_manual, nagad_manual.')
                ->maxLength(50)
                ->unique(table: 'payment_methods', column: 'code', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', tenant()?->id ?? 0)),

            TextInput::make('name')
                ->label('Internal Name')
                ->required()
                ->maxLength(255),

            TextInput::make('display_name')
                ->label('Display Name (customer-facing)')
                ->placeholder(fn (Get $get): ?string => $get('name'))
                ->helperText('Shown at checkout. Falls back to Name if empty.')
                ->maxLength(255),

            Select::make('type')
                ->options(collect(PaymentMethodType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->required()
                ->live(),

            Select::make('provider')
                ->label('MFS Provider')
                ->options(['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket'])
                ->visible(fn (Get $get): bool => $get('type') === PaymentMethodType::ManualMfs->value)
                ->helperText('Required for Manual MFS.'),

            TextInput::make('account_number')
                ->label('Account / Wallet Number')
                ->visible(fn (Get $get): bool => in_array($get('type'), [PaymentMethodType::ManualMfs->value, PaymentMethodType::BankTransfer->value], true))
                ->helperText('bKash/Nagad/Rocket number or bank account number.')
                ->maxLength(100),

            TextInput::make('account_name')
                ->label('Account Holder Name')
                ->visible(fn (Get $get): bool => in_array($get('type'), [PaymentMethodType::ManualMfs->value, PaymentMethodType::BankTransfer->value], true))
                ->maxLength(255),

            TextInput::make('bank_name')
                ->label('Bank Name')
                ->visible(fn (Get $get): bool => $get('type') === PaymentMethodType::BankTransfer->value)
                ->maxLength(255),

            TextInput::make('branch_name')
                ->label('Branch')
                ->visible(fn (Get $get): bool => $get('type') === PaymentMethodType::BankTransfer->value)
                ->maxLength(255),

            Textarea::make('instructions')
                ->label('Customer Instructions')
                ->visible(fn (Get $get): bool => in_array($get('type'), [PaymentMethodType::Cod->value, PaymentMethodType::ManualMfs->value, PaymentMethodType::BankTransfer->value], true))
                ->helperText('Shown at checkout for this method. Include reference instructions for manual payments.')
                ->rows(3)
                ->columnSpanFull(),

            // Online gateway configuration — shop-owned, foundation only (drivers not yet implemented)
            Select::make('gateway_driver')
                ->label('Gateway Driver')
                ->options(fn (): array => array_combine(array_keys(config('payment_gateways.drivers')), array_keys(config('payment_gateways.drivers'))) ?: ['sslcommerz' => 'sslcommerz'])
                ->visible(fn (Get $get): bool => in_array($get('type'), [PaymentMethodType::OnlineGateway->value, PaymentMethodType::Aggregator->value], true))
                ->helperText('Online gateway drivers are configuration-ready. Driver implementation arrives in a later phase.'),

            Select::make('gateway_mode')
                ->label('Gateway Mode')
                ->options(['live' => 'Live', 'sandbox' => 'Sandbox'])
                ->default('live')
                ->visible(fn (Get $get): bool => in_array($get('type'), [PaymentMethodType::OnlineGateway->value, PaymentMethodType::Aggregator->value], true))
                ->helperText('Sandbox/live toggle is stored now; enforcement comes with gateway drivers.'),

            Textarea::make('credentials')
                ->label('Credentials (JSON, encrypted at rest)')
                ->visible(fn (Get $get): bool => in_array($get('type'), [PaymentMethodType::OnlineGateway->value, PaymentMethodType::Aggregator->value], true))
                ->helperText('Stored encrypted. No secrets are exposed to the storefront. Leave empty until gateway drivers are available.')
                ->rows(2)
                ->columnSpanFull()
                ->disabled()
                ->dehydrated(false),

            Section::make('Fees & Limits (configuration-ready)')
                ->description('Stored now; fee-to-total calculation is deferred.')
                ->collapsed()
                ->schema([
                    Select::make('fee_type')
                        ->label('Fee Type')
                        ->options(['fixed' => 'Fixed', 'percent' => 'Percent'])
                        ->placeholder('No fee'),
                    TextInput::make('fee_value')
                        ->label('Fee Value')
                        ->helperText('Minor units if fixed (e.g. 5000 = ৳50.00), basis points if percent.')
                        ->numeric()
                        ->visible(fn (Get $get): bool => filled($get('fee_type'))),
                    TextInput::make('min_order_amount')
                        ->label('Min Order Amount (BDT)')
                        ->numeric()
                        ->helperText('Minor units. Enforced at checkout when present.'),
                    TextInput::make('max_order_amount')
                        ->label('Max Order Amount (BDT)')
                        ->numeric()
                        ->helperText('Minor units. Enforced at checkout when present.'),
                ]),

            Toggle::make('requires_verification')
                ->label('Requires Verification')
                ->helperText('Manual MFS / Bank Transfer require staff verification of the customer TrxID. COD is separate and does not use this.')
                ->default(fn (Get $get): bool => in_array($get('type'), [PaymentMethodType::ManualMfs->value, PaymentMethodType::BankTransfer->value], true))
                ->visible(fn (Get $get): bool => $get('type') !== PaymentMethodType::Cod->value),

            Toggle::make('is_active')
                ->label('Enabled')
                ->helperText('Inactive methods are hidden at checkout.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->state(fn (PaymentMethod $record): string => $record->displayName())
                    ->searchable(query: fn ($query, string $search) => $query->where('name', 'like', "%{$search}%")->orWhere('display_name', 'like', "%{$search}%")),
                TextColumn::make('type')->badge(),
                TextColumn::make('provider')->badge()->placeholder('—'),
                TextColumn::make('account_number')
                    ->label('Account')
                    ->formatStateUsing(fn (?string $state): string => $state ? '****'.substr($state, -4) : '—')
                    ->placeholder('—'),
                IconColumn::make('requires_verification')->boolean()->label('Verify'),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
