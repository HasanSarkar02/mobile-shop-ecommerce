<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Filament\Platform\Resources\SubscriptionPaymentResource\Pages;
use App\Filament\Platform\Support\PlatformMoney;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionPaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
use Throwable;
use UnitEnum;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Subscription Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('subscription_charge_id')
                ->label('Subscription charge')
                ->options(fn (): array => SubscriptionCharge::query()
                    ->with(['tenant', 'plan'])
                    ->whereIn('status', [
                        SubscriptionChargeStatus::Open->value,
                        SubscriptionChargeStatus::PartiallyPaid->value,
                    ])
                    ->get()
                    ->mapWithKeys(function (SubscriptionCharge $charge): array {
                        $tenant = $charge->tenant;
                        $plan = $charge->plan;
                        $tenantLabel = $tenant instanceof Tenant ? $tenant->name : 'Tenant removed';
                        $planLabel = $plan instanceof Plan ? $plan->name : 'Plan removed';
                        $outstanding = $charge->outstandingAmount() / 100;

                        return [
                            $charge->id => $tenantLabel.' — '.$planLabel.' ('.$outstanding.' BDT outstanding)',
                        ];
                    })
                    ->all())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $charge = $state === null ? null : SubscriptionCharge::query()->find((int) $state);
                    $outstanding = $charge?->outstandingAmount();

                    $rawIntent = $charge?->getAttribute('intent');
                    $intent = $rawIntent instanceof SubscriptionPaymentIntent
                        ? $rawIntent->value
                        : ($rawIntent === null ? null : (string) $rawIntent);

                    $set('amount', $outstanding !== null ? $outstanding / 100 : null);
                    $set('intent', $intent);
                })
                ->required(),
            Select::make('intent')
                ->options(fn (): array => collect(SubscriptionPaymentIntent::cases())
                    ->mapWithKeys(fn ($intent): array => [$intent->value => $intent->label()])
                    ->all())
                ->hidden()
                ->disabled()
                ->dehydrated(false),
            TextInput::make('extension_days')
                ->label('Extension days')
                ->numeric()
                ->required()
                ->minValue(1)
                ->integer()
                ->visible(fn (Get $get): bool => $get('intent') === SubscriptionPaymentIntent::ExtendSubscription->value),
            Select::make('payment_method')
                ->label('MFS method')
                ->options([
                    'bkash' => 'bKash',
                    'nagad' => 'Nagad',
                    'rocket' => 'Rocket',
                    'other' => 'Other / Manual',
                ])
                ->default('other')
                ->required(),
            TextInput::make('reference')
                ->label('Transaction reference (TrxID)')
                ->required()
                ->maxLength(255)
                ->helperText('The buyer MFS TrxID. Trimmed and uppercased automatically.'),
            TextInput::make('amount')
                ->label('Amount (BDT)')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->step(0.01)
                ->live()
                ->helperText(fn (Get $get): string => self::remainingBalanceHelper($get)),
            Textarea::make('note')->rows(3),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Payment')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('tenant.name')->label('Tenant'),
                        TextEntry::make('intent')->label('Intent')->state(fn (SubscriptionPayment $record): string => self::intentLabel($record))->badge(),
                        TextEntry::make('plan.name')->label('Plan')->placeholder('—'),
                        TextEntry::make('amount')->label('Amount')->money('BDT', divideBy: 100),
                        TextEntry::make('currency')->label('Currency'),
                        TextEntry::make('provider')->label('Provider'),
                        TextEntry::make('payment_method')->label('Payment method'),
                        TextEntry::make('reference')->label('Reference')->copyable(),
                        TextEntry::make('status')->label('Status')->state(fn (SubscriptionPayment $record): string => self::statusLabel($record))->badge(),
                        TextEntry::make('extension_days')->label('Extension days')->placeholder('—'),
                    ]),
                ]),
            Section::make('Timeline')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_at')->label('Recorded at')->dateTime(),
                        TextEntry::make('received_at')->label('Received at')->dateTime()->placeholder('—'),
                        TextEntry::make('rejected_at')->label('Rejected at')->dateTime()->placeholder('—'),
                        TextEntry::make('rejected_reason')->label('Rejection reason')->placeholder('—'),
                    ]),
                ]),
            Section::make('Actors')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('creator.name')->label('Created by')->placeholder('—'),
                        TextEntry::make('verifier.name')->label('Verified by')->placeholder('—'),
                        TextEntry::make('rejector.name')->label('Rejected by')->placeholder('—'),
                    ]),
                ]),
            Section::make('Note')
                ->schema([
                    TextEntry::make('note')->label('Note')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Tenant')->searchable(),
                TextColumn::make('intent')->badge(),
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('amount')->money('BDT', divideBy: 100),
                TextColumn::make('provider'),
                TextColumn::make('reference')->searchable()->copyable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->options(fn (): array => collect(SubscriptionPaymentStatus::cases())
                        ->mapWithKeys(fn ($status): array => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('intent')
                    ->options(fn (): array => collect(SubscriptionPaymentIntent::cases())
                        ->mapWithKeys(fn ($intent): array => [$intent->value => $intent->label()])
                        ->all()),
                SelectFilter::make('provider')
                    ->options(['manual' => 'Manual']),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                Action::make('verify')
                    ->label('Verify')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (SubscriptionPayment $record): bool => self::isPending($record))
                    ->requiresConfirmation()
                    ->action(function (SubscriptionPayment $record): void {
                        try {
                            $actor = auth('platform')->user();
                            abort_unless($actor instanceof User, 403);

                            app(SubscriptionPaymentService::class)->verify($record, $actor);
                            Notification::make()->success()->title('Payment verified.')->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Verification failed.')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (SubscriptionPayment $record): bool => self::isPending($record))
                    ->schema([Textarea::make('reason')->label('Rejection reason')->required()->rows(3)])
                    ->action(function (SubscriptionPayment $record, array $data): void {
                        try {
                            $actor = auth('platform')->user();
                            abort_unless($actor instanceof User, 403);

                            app(SubscriptionPaymentService::class)->reject($record, $actor, (string) ($data['reason'] ?? ''));
                            Notification::make()->success()->title('Payment rejected.')->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Rejection failed.')->body($exception->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tenant', 'plan', 'creator', 'verifier', 'rejector']);
    }

    public static function canViewAny(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canView(Model $record): bool
    {
        return self::canAccessPlatform() && $record instanceof SubscriptionPayment;
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
            'index' => Pages\ListSubscriptionPayments::route('/'),
            'create' => Pages\CreateSubscriptionPayment::route('/create'),
            'view' => Pages\ViewSubscriptionPayment::route('/{record}'),
        ];
    }

    private static function isPending(SubscriptionPayment $record): bool
    {
        return $record->getAttribute('status') === SubscriptionPaymentStatus::Pending;
    }

    private static function intentLabel(SubscriptionPayment $record): string
    {
        $intent = $record->getAttribute('intent');

        return $intent instanceof SubscriptionPaymentIntent ? $intent->label() : (string) $intent;
    }

    private static function statusLabel(SubscriptionPayment $record): string
    {
        $status = $record->getAttribute('status');

        return $status instanceof SubscriptionPaymentStatus ? $status->label() : (string) $status;
    }

    private static function canAccessPlatform(): bool
    {
        return config('deployment.mode') === DeploymentMode::SaaS->value
            && auth('platform')->user()?->is_platform_admin === true;
    }

    private static function remainingBalanceHelper(Get $get): string
    {
        $chargeId = $get('subscription_charge_id');

        if ($chargeId === null || $chargeId === '') {
            return 'In BDT (major units). Prefilled from the outstanding charge balance.';
        }

        $charge = SubscriptionCharge::query()->find((int) $chargeId);

        if ($charge === null) {
            return 'In BDT (major units).';
        }

        $outstanding = $charge->outstandingAmount();
        $amount = (float) ($get('amount') ?? 0);
        $remaining = $outstanding - (int) round($amount * 100);

        if ($amount > 0) {
            return 'In BDT (major units). Outstanding: '.PlatformMoney::format($outstanding)
                .' · Remaining after this payment: '.PlatformMoney::format(max(0, $remaining));
        }

        return 'In BDT (major units). Outstanding: '.PlatformMoney::format($outstanding);
    }
}
