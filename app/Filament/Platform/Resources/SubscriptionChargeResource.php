<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionDiscountType;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionPaymentIntent;
use App\Filament\Platform\Resources\SubscriptionChargeResource\Pages;
use App\Filament\Platform\Support\PlatformMoney;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SubscriptionChargeResource extends Resource
{
    protected static ?string $model = SubscriptionCharge::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Subscription Charges';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tenant_id')
                ->label('Tenant')
                ->options(fn (): array => Tenant::query()
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Tenant $tenant): array => [
                        $tenant->id => $tenant->name.' ('.$tenant->subdomain.')',
                    ])
                    ->all())
                ->searchable()
                ->preload()
                ->required(),
            Select::make('intent')
                ->label('Intent')
                ->options(fn (): array => collect(SubscriptionPaymentIntent::cases())
                    ->mapWithKeys(fn ($intent): array => [$intent->value => $intent->label()])
                    ->all())
                ->default(SubscriptionPaymentIntent::AssignPlan->value)
                ->live()
                ->required(),
            Select::make('plan_id')
                ->label('Plan')
                ->options(fn (): array => Plan::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->mapWithKeys(fn (Plan $plan): array => [$plan->id => $plan->name])
                    ->all())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    $price = $state === null ? null : Plan::query()->find((int) $state)?->price;
                    $set('base_amount', $price !== null ? $price / 100 : null);
                    self::applyAmountPreview($set, $get);
                })
                ->required(),
            DateTimePicker::make('period_starts_at')
                ->label('Period starts')
                ->helperText('Optional. Leave blank to defer the period to settlement.'),
            DateTimePicker::make('period_ends_at')
                ->label('Period ends'),
            TextInput::make('base_amount')
                ->label('Base amount (BDT)')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->step(0.01)
                ->live()
                ->helperText('Prefilled from the plan price. Override for a negotiated base amount.')
                ->afterStateUpdated(function (Set $set, Get $get): void {
                    self::applyAmountPreview($set, $get);
                }),
            Select::make('discount_type')
                ->label('Discount type')
                ->options([
                    SubscriptionDiscountType::Percentage->value => 'Percentage (%)',
                    SubscriptionDiscountType::Fixed->value => 'Fixed amount (BDT)',
                ])
                ->placeholder('No discount')
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get): void {
                    self::applyAmountPreview($set, $get);
                }),
            TextInput::make('discount_value')
                ->label('Discount value')
                ->numeric()
                ->minValue(0.01)
                ->step(0.01)
                ->live()
                ->helperText(fn (Get $get): string => $get('discount_type') === SubscriptionDiscountType::Percentage->value
                    ? 'Percentage off the base amount (1–100).'
                    : 'Fixed amount in BDT.')
                ->afterStateUpdated(function (Set $set, Get $get): void {
                    self::applyAmountPreview($set, $get);
                }),
            TextInput::make('discount_amount')
                ->label('Discount amount (BDT)')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->default(0),
            TextInput::make('net_amount')
                ->label('Net amount (BDT)')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->default(0),
            Textarea::make('note')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Tenant')->searchable(),
                TextColumn::make('intent')
                    ->label('Intent')
                    ->state(fn (SubscriptionCharge $record): string => self::intentLabel($record))
                    ->badge(),
                TextColumn::make('plan.name')->label('Plan')->placeholder('—'),
                TextColumn::make('base_amount')
                    ->label('Base')
                    ->formatStateUsing(fn ($state): string => PlatformMoney::format((int) $state)),
                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state): string => PlatformMoney::format((int) $state)),
                TextColumn::make('net_amount')
                    ->label('Net')
                    ->formatStateUsing(fn ($state): string => PlatformMoney::format((int) $state)),
                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state): string => PlatformMoney::format((int) $state)),
                TextColumn::make('outstanding_amount')
                    ->label('Outstanding')
                    ->state(fn (SubscriptionCharge $record): string => PlatformMoney::format($record->outstandingAmount())),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (SubscriptionCharge $record): string => self::statusLabel($record))
                    ->badge(),
                TextColumn::make('period_ends_at')->label('Period ends')->dateTime()->placeholder('—'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('intent')
                    ->label('Intent')
                    ->options(fn (): array => collect(SubscriptionPaymentIntent::cases())
                        ->mapWithKeys(fn ($intent): array => [$intent->value => $intent->label()])
                        ->all()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => collect(SubscriptionChargeStatus::cases())
                        ->mapWithKeys(fn ($status): array => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('outstanding')
                    ->label('Status')
                    ->options(['open_or_partially_paid' => 'Open / Partially Paid'])
                    ->query(function (Builder $query, array $data): void {
                        if (($data['value'] ?? null) !== 'open_or_partially_paid') {
                            return;
                        }

                        $query
                            ->whereIn('status', [
                                SubscriptionChargeStatus::Open->value,
                                SubscriptionChargeStatus::PartiallyPaid->value,
                            ])
                            ->whereColumn('net_amount', '>', 'paid_amount');
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Charge')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('tenant.name')->label('Tenant'),
                        TextEntry::make('plan.name')->label('Plan')->placeholder('—'),
                        TextEntry::make('intent')
                            ->label('Intent')
                            ->state(fn (SubscriptionCharge $record): string => self::intentLabel($record))
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->state(fn (SubscriptionCharge $record): string => self::statusLabel($record))
                            ->badge(),
                        TextEntry::make('period_starts_at')->label('Period starts')->dateTime()->placeholder('—'),
                        TextEntry::make('period_ends_at')->label('Period ends')->dateTime()->placeholder('—'),
                    ]),
                ]),
            Section::make('Amounts')
                ->description('All amounts are snapshotted at creation and never change while the charge is open.')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('base_amount')
                            ->label('Base')
                            ->state(fn (SubscriptionCharge $record): string => PlatformMoney::format((int) $record->base_amount)),
                        TextEntry::make('discount_type')
                            ->label('Discount type')
                            ->state(fn (SubscriptionCharge $record): string => self::discountLabel($record)),
                        TextEntry::make('discount_amount')
                            ->label('Discount amount')
                            ->state(fn (SubscriptionCharge $record): string => PlatformMoney::format((int) $record->discount_amount)),
                        TextEntry::make('net_amount')
                            ->label('Net')
                            ->state(fn (SubscriptionCharge $record): string => PlatformMoney::format((int) $record->net_amount)),
                        TextEntry::make('paid_amount')
                            ->label('Paid')
                            ->state(fn (SubscriptionCharge $record): string => PlatformMoney::format((int) $record->paid_amount)),
                        TextEntry::make('outstanding_amount')
                            ->label('Outstanding')
                            ->state(fn (SubscriptionCharge $record): string => PlatformMoney::format($record->outstandingAmount())),
                    ]),
                ]),
            Section::make('Settlement')
                ->schema([
                    TextEntry::make('settlement')
                        ->label('Result')
                        ->state(fn (SubscriptionCharge $record): string => self::settlementResult($record))
                        ->columnSpanFull(),
                ]),
            Section::make('Audit')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_by')
                            ->label('Created by')
                            ->state(fn (SubscriptionCharge $record): string => self::createdByName($record)),
                        TextEntry::make('created_at')->label('Created at')->dateTime(),
                    ]),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tenant', 'plan', 'createdBy']);
    }

    public static function canViewAny(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canView(Model $record): bool
    {
        return self::canAccessPlatform() && $record instanceof SubscriptionCharge;
    }

    public static function canCreate(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionCharges::route('/'),
            'create' => Pages\CreateSubscriptionCharge::route('/create'),
            'view' => Pages\ViewSubscriptionCharge::route('/{record}'),
        ];
    }

    private static function applyAmountPreview(Set $set, Get $get): void
    {
        $base = (int) round(((float) ($get('base_amount') ?? 0)) * 100);
        $rawType = $get('discount_type');
        $type = is_string($rawType) && $rawType !== ''
            ? SubscriptionDiscountType::tryFrom($rawType)
            : null;
        $rawValue = (float) ($get('discount_value') ?? 0);

        $discount = match ($type) {
            SubscriptionDiscountType::Percentage => min(intdiv($base * (int) $rawValue, 100), $base),
            SubscriptionDiscountType::Fixed => min((int) round($rawValue * 100), $base),
            null => 0,
        };

        $set('discount_amount', $discount / 100);
        $set('net_amount', ($base - $discount) / 100);
    }

    private static function intentLabel(SubscriptionCharge $record): string
    {
        $intent = $record->getAttribute('intent');

        return $intent instanceof SubscriptionPaymentIntent ? $intent->label() : (string) $intent;
    }

    private static function statusLabel(SubscriptionCharge $record): string
    {
        $status = $record->getAttribute('status');

        return $status instanceof SubscriptionChargeStatus ? $status->label() : (string) $status;
    }

    private static function discountLabel(SubscriptionCharge $record): string
    {
        $type = $record->getAttribute('discount_type');

        if ($type === null) {
            return 'No discount';
        }

        return ($type instanceof SubscriptionDiscountType ? $type->label() : (string) $type).' · '.PlatformMoney::format((int) $record->discount_amount);
    }

    private static function createdByName(SubscriptionCharge $record): string
    {
        $user = $record->createdBy;

        return $user instanceof User ? (string) $user->name : 'System';
    }

    private static function settlementResult(SubscriptionCharge $record): string
    {
        if (! $record->isPaid()) {
            return 'Not settled — this charge is not fully paid yet.';
        }

        $event = SubscriptionEvent::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', (int) $record->tenant_id)
            ->where('note', 'like', '%charge #'.$record->id.',%')
            ->orderByDesc('id')
            ->first();

        if ($event === null) {
            return 'Settled — the subscription was updated when the final payment was verified.';
        }

        $type = $event->getAttribute('type');
        $typeLabel = $type instanceof SubscriptionEventType ? $type->label() : (string) $type;

        return 'Settled via '.$typeLabel.'. '.($event->note ?? '');
    }

    private static function canAccessPlatform(): bool
    {
        return config('deployment.mode') === DeploymentMode::SaaS->value
            && auth('platform')->user()?->is_platform_admin === true;
    }
}
