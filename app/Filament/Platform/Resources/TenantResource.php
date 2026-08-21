<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\DomainsRelationManager;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\OwnersRelationManager;
use App\Filament\Platform\Support\SubscriptionHistoryPresenter;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Rules\BangladeshiPhone;
use App\Rules\ValidSubdomain;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Spatie\Activitylog\Models\Activity;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('subdomain')
                ->required()
                ->unique(ignoreRecord: true)
                ->rules([new ValidSubdomain]),
            Select::make('plan')
                ->label('Plan')
                ->options(fn (): array => Plan::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->mapWithKeys(fn (Plan $plan): array => [$plan->slug => $plan->name])
                    ->all())
                ->required()
                ->hiddenOn('edit'),
            TextInput::make('owner_name')
                ->label('Owner name')
                ->required()
                ->maxLength(255)
                ->hiddenOn('edit'),
            TextInput::make('owner_email')
                ->label('Owner email')
                ->email()
                ->required()
                ->unique(table: 'users', column: 'email', modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('tenant_id'))
                ->hiddenOn('edit'),
            TextInput::make('owner_phone')
                ->label('Owner phone')
                ->tel()
                ->maxLength(20)
                ->rules([new BangladeshiPhone])
                ->helperText('Optional — so the platform can reach the owner.')
                ->hiddenOn('edit'),
            Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'trial' => 'Trial',
                    'active' => 'Active',
                    'suspended' => 'Suspended',
                    'rejected' => 'Rejected',
                ])
                ->disabled()
                ->dehydrated(false)
                ->hiddenOn('create'),
            TextInput::make('contact_email')->email(),
            TextInput::make('contact_phone'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('subdomain')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('plan'),
                TextColumn::make('created_at')->date(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Tenant')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')->label('Name'),
                        TextEntry::make('subdomain')->label('Subdomain'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('contact_email')->label('Contact email')->placeholder('—'),
                        TextEntry::make('contact_phone')->label('Contact phone')->placeholder('—'),
                        TextEntry::make('created_at')->label('Created')->dateTime(),
                    ]),
                ]),
            Section::make('Subscription')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('subscription_plan')
                            ->label('Authoritative plan')
                            ->state(fn (Tenant $record): string => (string) (self::subscription($record)?->entitlement('plan_name') ?? 'No subscription')),
                        TextEntry::make('subscription_status')
                            ->label('Subscription status')
                            ->badge()
                            ->state(fn (Tenant $record): string => self::subscriptionStatus(self::subscription($record))),
                        TextEntry::make('subscription_type')
                            ->label('Subscription type')
                            ->state(fn (Tenant $record): string => self::subscriptionType(self::subscription($record))),
                        TextEntry::make('period_starts_at')
                            ->label('Period starts')
                            ->state(fn (Tenant $record): ?string => self::periodDate(self::subscription($record), 'current_period_starts_at'))
                            ->placeholder('—'),
                        TextEntry::make('period_ends_at')
                            ->label('Period ends / expiry')
                            ->state(fn (Tenant $record): ?string => self::periodDate(self::subscription($record), 'current_period_ends_at'))
                            ->placeholder('—'),
                        TextEntry::make('days_remaining')
                            ->label('Days remaining')
                            ->state(fn (Tenant $record): string => self::subscription($record) ? (string) self::subscription($record)->daysRemaining() : '—'),
                    ]),
                ]),
            Section::make('Domains')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('domain_count')
                            ->label('Total domains')
                            ->state(fn (Tenant $record): string => (string) count(self::domains($record))),
                        TextEntry::make('primary_domain')
                            ->label('Primary domain')
                            ->state(fn (Tenant $record): string => self::primaryDomain($record)),
                        TextEntry::make('active_domain_count')
                            ->label('Active custom domains')
                            ->state(fn (Tenant $record): string => (string) count(array_filter(self::domains($record), fn (Domain $domain): bool => self::domainStatus($domain) === DomainStatus::Active))),
                        TextEntry::make('pending_domain_count')
                            ->label('Pending verification')
                            ->state(fn (Tenant $record): string => (string) count(array_filter(self::domains($record), fn (Domain $domain): bool => self::domainStatus($domain) === DomainStatus::Pending))),
                    ]),
                    TextEntry::make('domain_management')
                        ->label('Domain management')
                        ->state('Use the Custom Domains relation below to open an existing domain.'),
                ]),
            Section::make('Owner summary')
                ->schema([
                    TextEntry::make('owners')
                        ->label('Owners')
                        ->state(fn (Tenant $record): string => self::ownerSummary($record))
                        ->columnSpanFull(),
                ]),
            Section::make('Usage')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('product_usage')
                            ->label('Products used')
                            ->state(fn (Tenant $record): string => (string) Product::query()->withoutGlobalScope('tenant')->where('tenant_id', $record->id)->count()),
                        TextEntry::make('product_limit')
                            ->label('Product quota')
                            ->state(fn (Tenant $record): string => self::quota(self::entitlementLimit(self::subscription($record), 'max_products'))),
                        TextEntry::make('staff_usage')
                            ->label('Staff used')
                            ->state(fn (Tenant $record): string => (string) User::query()->where('tenant_id', $record->id)->where('role', 'staff')->count()),
                        TextEntry::make('staff_limit')
                            ->label('Staff quota')
                            ->state(fn (Tenant $record): string => self::quota(self::entitlementLimit(self::subscription($record), 'max_staff'))),
                    ]),
                ]),
            Section::make('Recent activity')
                ->schema([
                    ViewEntry::make('subscription_history')
                        ->label('Subscription timeline')
                        ->state(fn (Tenant $record): array => SubscriptionHistoryPresenter::items($record, 50))
                        ->view('filament.platform.subscription-timeline'),
                    TextEntry::make('domain_activity')
                        ->label('Domain activity')
                        ->state(fn (Tenant $record): string => self::domainActivity($record))
                        ->placeholder('No domain activity.'),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'subscription.plan',
            'domains',
            'primaryDomain',
            'primaryOwner',
        ]);
    }

    public static function canViewAny(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canView(Model $record): bool
    {
        return self::canAccessPlatform() && $record instanceof Tenant;
    }

    public static function getRelations(): array
    {
        return [DomainsRelationManager::class, OwnersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    private static function canAccessPlatform(): bool
    {
        return config('deployment.mode') === DeploymentMode::SaaS->value
            && auth('platform')->user()?->is_platform_admin === true;
    }

    private static function ownerSummary(Tenant $tenant): string
    {
        $primaryOwnerId = (int) $tenant->getAttribute('primary_owner_id');
        $owners = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->orderBy('id')
            ->get();

        if ($owners->isEmpty()) {
            return 'No owner found.';
        }

        return $owners->map(fn (User $owner): string => sprintf(
            '%s <%s> — role: %s — phone: %s — email: %s',
            $owner->name,
            $owner->email,
            $owner->id === $primaryOwnerId ? 'primary owner' : 'additional owner',
            $owner->phone ?: '—',
            self::emailVerification($owner),
        ))->prepend($owners->count().' owner(s)')->implode("\n");
    }

    private static function quota(?int $limit): string
    {
        return $limit === null ? 'Unlimited' : (string) $limit;
    }

    private static function entitlementLimit(?TenantSubscription $subscription, string $attribute): ?int
    {
        if ($subscription === null) {
            return null;
        }

        $value = $subscription->entitlement($attribute);

        return $value === null ? null : (int) $value;
    }

    private static function emailVerification(User $owner): string
    {
        $verifiedAt = $owner->getAttribute('email_verified_at');

        return $verifiedAt instanceof CarbonInterface ? $verifiedAt->toDateTimeString() : 'not verified';
    }

    private static function domainActivity(Tenant $tenant): string
    {
        $domainIds = array_map(fn (Domain $domain): int => (int) $domain->getKey(), self::domains($tenant));

        if ($domainIds === []) {
            return '';
        }

        return Activity::query()
            ->where('log_name', 'domains')
            ->where('subject_type', Domain::class)
            ->whereIn('subject_id', $domainIds)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Activity $activity): string => sprintf(
                '%s — %s',
                self::createdAt($activity),
                (string) $activity->getAttribute('description'),
            ))
            ->implode("\n");
    }

    private static function subscription(Tenant $tenant): ?TenantSubscription
    {
        $subscription = $tenant->relationLoaded('subscription')
            ? $tenant->getRelation('subscription')
            : $tenant->subscription;

        return $subscription instanceof TenantSubscription ? $subscription : null;
    }

    private static function subscriptionStatus(?TenantSubscription $subscription): string
    {
        $status = $subscription?->getAttribute('status');

        return $status instanceof SubscriptionStatus ? $status->label() : 'No subscription';
    }

    private static function subscriptionType(?TenantSubscription $subscription): string
    {
        if ($subscription === null) {
            return '—';
        }

        return $subscription->isTrialing() ? 'Trial' : 'Paid';
    }

    private static function periodDate(?TenantSubscription $subscription, string $attribute): ?string
    {
        $date = $subscription?->getAttribute($attribute);

        return $date instanceof CarbonInterface ? $date->toDateTimeString() : null;
    }

    /** @return list<Domain> */
    private static function domains(Tenant $tenant): array
    {
        $relation = $tenant->relationLoaded('domains')
            ? $tenant->getRelation('domains')
            : $tenant->domains;

        if (! $relation instanceof Collection) {
            return [];
        }

        $domains = [];

        foreach ($relation as $domain) {
            if ($domain instanceof Domain) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    private static function primaryDomain(Tenant $tenant): string
    {
        $domain = $tenant->relationLoaded('primaryDomain')
            ? $tenant->getRelation('primaryDomain')
            : $tenant->primaryDomain;

        return $domain instanceof Domain ? (string) $domain->getAttribute('domain') : 'Tenant subdomain fallback';
    }

    private static function domainStatus(Domain $domain): ?DomainStatus
    {
        $status = $domain->getAttribute('status');

        return $status instanceof DomainStatus ? $status : DomainStatus::tryFrom((string) $status);
    }

    private static function createdAt(Model $model): string
    {
        $createdAt = $model->getAttribute('created_at');

        return $createdAt instanceof CarbonInterface ? $createdAt->toDateTimeString() : 'Unknown time';
    }
}
