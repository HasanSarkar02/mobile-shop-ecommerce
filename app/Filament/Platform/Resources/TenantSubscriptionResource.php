<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\TenantSubscriptionResource\Pages;
use App\Filament\Platform\Support\SubscriptionHistoryPresenter;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TenantSubscriptionResource extends Resource
{
    protected static ?string $model = TenantSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Subscriptions';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Tenant')->searchable()->sortable(),
                TextColumn::make('tenant.subdomain')->label('Subdomain')->searchable()->sortable(),
                TextColumn::make('plan_name')
                    ->label('Plan')
                    ->state(fn (TenantSubscription $record): string => self::planName($record)),
                TextColumn::make('status')->badge(),
                TextColumn::make('current_period_starts_at')->label('Period starts')->dateTime()->placeholder('—'),
                TextColumn::make('current_period_ends_at')->label('Period ends')->dateTime()->placeholder('—'),
                TextColumn::make('cancelled_at')->dateTime()->placeholder('—'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->options(fn (): array => collect(SubscriptionStatus::cases())
                        ->mapWithKeys(fn ($status): array => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('expiring')
                    ->label('Period')
                    ->options(['within_7_days' => 'Expiring within 7 days'])
                    ->query(function (Builder $query, array $data): void {
                        if (($data['value'] ?? null) !== 'within_7_days') {
                            return;
                        }

                        $query
                            ->whereIn('status', [
                                SubscriptionStatus::Active->value,
                                SubscriptionStatus::Trialing->value,
                            ])
                            ->whereBetween('current_period_ends_at', [now(), now()->addDays(7)]);
                    }),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->options(fn (): array => Plan::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Subscription')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('tenant.name')->label('Tenant'),
                        TextEntry::make('tenant.subdomain')->label('Subdomain'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('plan_name')
                            ->label('Plan')
                            ->state(fn (TenantSubscription $record): string => self::planName($record)),
                        TextEntry::make('billing_period')->label('Billing period')->placeholder('—'),
                        TextEntry::make('price')
                            ->label('Price')
                            ->money('BDT', divideBy: 100)
                            ->placeholder('—'),
                        TextEntry::make('current_period_starts_at')->label('Period starts')->dateTime()->placeholder('—'),
                        TextEntry::make('current_period_ends_at')->label('Period ends')->dateTime()->placeholder('—'),
                        TextEntry::make('days_remaining')
                            ->label('Days remaining')
                            ->state(fn (TenantSubscription $record): string => (string) $record->daysRemaining()),
                        TextEntry::make('cancelled_at')->label('Cancelled at')->dateTime()->placeholder('—'),
                    ]),
                ]),
            Section::make('Entitlement snapshot')
                ->description('Entitlements captured at assignment time. The snapshot is authoritative while the subscription is current.')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('max_products')
                            ->label('Product quota')
                            ->state(fn (TenantSubscription $record): string => self::quota($record->entitlement('max_products'))),
                        TextEntry::make('max_staff')
                            ->label('Staff quota')
                            ->state(fn (TenantSubscription $record): string => self::quota($record->entitlement('max_staff'))),
                        TextEntry::make('custom_domain_allowed')
                            ->label('Custom domains')
                            ->state(fn (TenantSubscription $record): string => self::customDomainEntitlement($record->entitlement('custom_domain_allowed'))),
                    ]),
                ]),
            Section::make('Activity history')
                ->schema([
                    ViewEntry::make('subscription_history')
                        ->state(function (TenantSubscription $record): array {
                            $tenant = $record->tenant;
                            abort_unless($tenant instanceof Tenant, 403);

                            return SubscriptionHistoryPresenter::items($tenant, 50);
                        })
                        ->view('filament.platform.subscription-timeline'),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tenant', 'plan']);
    }

    public static function canViewAny(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canView(Model $record): bool
    {
        return self::canAccessPlatform() && $record instanceof TenantSubscription;
    }

    public static function canCreate(): bool
    {
        return false;
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
            'index' => Pages\ListTenantSubscriptions::route('/'),
            'view' => Pages\ViewTenantSubscription::route('/{record}'),
        ];
    }

    public static function planName(TenantSubscription $record): string
    {
        return (string) ($record->entitlement('plan_name') ?? 'No plan');
    }

    private static function canAccessPlatform(): bool
    {
        return config('deployment.mode') === DeploymentMode::SaaS->value
            && auth('platform')->user()?->is_platform_admin === true;
    }

    private static function quota(mixed $value): string
    {
        return $value === null ? 'Unlimited' : (string) $value;
    }

    private static function customDomainEntitlement(mixed $value): string
    {
        if ($value === null) {
            return 'Unknown';
        }

        return $value ? 'Allowed' : 'Not allowed';
    }
}
