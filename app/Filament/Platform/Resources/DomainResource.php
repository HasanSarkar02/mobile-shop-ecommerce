<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Filament\Platform\Resources\DomainResource\Pages;
use App\Models\Domain;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Domains';

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
            TextInput::make('domain')
                ->label('Hostname')
                ->helperText('Enter a hostname only. Do not include https://, a path, port, or wildcard.')
                ->required()
                ->maxLength(253),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->searchable()->copyable(),
                TextColumn::make('tenant.name')->label('Tenant')->searchable(),
                TextColumn::make('tenant.subdomain')->label('Subdomain')->searchable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->state(fn (Domain $record): bool => self::isPrimaryForUi($record)),
                TextColumn::make('verified_at')->dateTime()->placeholder('Not verified'),
                TextColumn::make('activated_at')->dateTime()->placeholder('Not active'),
                TextColumn::make('verification_expires_at')->dateTime()->placeholder('—'),
                TextColumn::make('last_checked_at')->dateTime()->placeholder('—'),
                TextColumn::make('verification_attempts'),
                TextColumn::make('created_at')->dateTime(),
                TextColumn::make('entitlement')
                    ->label('Entitlement')
                    ->badge()
                    ->state(fn (Domain $record): string => self::isEntitled($record) ? 'Entitled' : 'Not entitled'),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->options(fn (): array => collect(DomainStatus::cases())
                        ->mapWithKeys(fn ($status): array => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('primary')
                    ->options(['primary' => 'Primary', 'alias' => 'Alias'])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === 'primary') {
                            return $query->whereIn(
                                'domains.id',
                                Tenant::query()->whereNotNull('primary_domain_id')->pluck('primary_domain_id'),
                            );
                        }

                        if ($value === 'alias') {
                            return $query->whereNotIn(
                                'domains.id',
                                Tenant::query()->whereNotNull('primary_domain_id')->pluck('primary_domain_id'),
                            );
                        }

                        return $query;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Domain')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('domain')->label('Hostname')->copyable(),
                        TextEntry::make('tenant.name')->label('Tenant'),
                        TextEntry::make('tenant.subdomain')->label('Tenant subdomain'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('entitlement')
                            ->label('Custom-domain entitlement')
                            ->badge()
                            ->state(fn (Domain $record): string => self::isEntitled($record) ? 'Entitled' : 'Not entitled'),
                        TextEntry::make('primary_state')
                            ->label('Canonical state')
                            ->state(fn (Domain $record): string => self::isPrimaryForUi($record) ? 'Primary domain' : 'Alias / fallback'),
                    ]),
                ]),
            Section::make('Verification')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('verification_method')->label('Method')->placeholder('—'),
                        TextEntry::make('verification_record_name')->label('TXT record name')->copyable()->placeholder('—'),
                        TextEntry::make('verification_record_value')
                            ->label('TXT record value')
                            ->copyable()
                            ->state(fn (Domain $record): ?string => self::challengeSessionData($record)['record_value'] ?? null)
                            ->placeholder('The TXT value is available while this verification challenge is valid.'),
                        TextEntry::make('verified_at')->dateTime()->placeholder('Not verified'),
                        TextEntry::make('verification_expires_at')->dateTime()->placeholder('—'),
                        TextEntry::make('verification_attempts'),
                        TextEntry::make('last_checked_at')->dateTime()->placeholder('—'),
                        TextEntry::make('verification_failure_code')->placeholder('—'),
                        TextEntry::make('verification_failure_message')->placeholder('—'),
                    ]),
                ]),
            Section::make('Lifecycle')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('activated_at')->dateTime()->placeholder('Not active'),
                        TextEntry::make('revoked_at')->dateTime()->placeholder('—'),
                        TextEntry::make('revocation_reason')->placeholder('—'),
                    ]),
                ]),
            Section::make('Activity history')
                ->schema([
                    TextEntry::make('activity_history')
                        ->hiddenLabel()
                        ->state(fn (Domain $record): string => Activity::query()
                            ->forSubject($record)
                            ->latest()
                            ->get()
                            ->map(fn (Activity $activity): string => sprintf(
                                '%s — %s',
                                $activity->created_at?->toDateTimeString() ?? 'Unknown time',
                                $activity->description,
                            ))
                            ->implode("\n"))
                        ->placeholder('No activity recorded.'),
                ]),
        ]);
    }

    public static function canViewAny(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canView(Model $record): bool
    {
        return self::canAccessPlatform() && $record instanceof Domain;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function challengeSessionKey(int $domainId): string
    {
        return 'platform.domain-challenge.'.$domainId;
    }

    /** @return array{domain_id?: int, record_name?: string, record_value?: string, expires_at?: string} */
    public static function challengeSessionData(Domain $record): array
    {
        $data = session()->get(self::challengeSessionKey($record->id), []);

        if (! $record->exists || $record->getKey() === null || ! is_array($data)) {
            return [];
        }

        if ((int) ($data['domain_id'] ?? 0) !== (int) $record->getKey()) {
            return [];
        }

        $status = $record->getAttribute('status');
        $status = $status instanceof DomainStatus
            ? $status
            : DomainStatus::tryFrom((string) $status);

        if (! in_array($status, [DomainStatus::Pending, DomainStatus::Failed], true)) {
            return [];
        }

        $value = $data['record_value'] ?? null;
        $digest = $record->getAttribute('verification_token_digest');

        if (! is_string($value) || $value === '' || ! is_string($digest) || $digest === ''
            || ! hash_equals($digest, hash('sha256', $value))) {
            return [];
        }

        $expiresAt = $record->getAttribute('verification_expires_at');

        if (! $expiresAt instanceof CarbonInterface || $expiresAt->isPast()) {
            return [];
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'view' => Pages\ViewDomain::route('/{record}'),
        ];
    }

    private static function canAccessPlatform(): bool
    {
        return config('deployment.mode') === DeploymentMode::SaaS->value
            && auth('platform')->user()?->is_platform_admin === true;
    }

    public static function isPrimaryForUi(Domain $record): bool
    {
        return (int) Tenant::query()->whereKey($record->getAttribute('tenant_id'))->value('primary_domain_id') === (int) $record->id;
    }

    private static function isEntitled(Domain $record): bool
    {
        $tenant = Tenant::query()->find($record->getAttribute('tenant_id'));

        return $tenant !== null && app(SubscriptionService::class)->canUseCustomDomain($tenant);
    }
}
