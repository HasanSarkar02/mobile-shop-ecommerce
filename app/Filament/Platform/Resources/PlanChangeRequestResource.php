<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\PlanChangeRequestStatus;
use App\Filament\Platform\Resources\PlanChangeRequestResource\Pages;
use App\Models\PlanChangeRequest;
use App\Services\SubscriptionService;
use App\Support\Tenancy\Tenancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanChangeRequestResource extends Resource
{
    protected static ?string $model = PlanChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Plan Change Requests';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime(),
                TextColumn::make('tenant.name')->label('Tenant'),
                TextColumn::make('requestedPlan.name')->label('Requested Plan'),
                TextColumn::make('note')->limit(30)->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->visible(fn (PlanChangeRequest $record): bool => $record->status === PlanChangeRequestStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function (PlanChangeRequest $record): void {
                        app(SubscriptionService::class)->changePlan($record->tenant, $record->requestedPlan);
                        static::updateAsTenant($record, PlanChangeRequestStatus::Approved);
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (PlanChangeRequest $record): bool => $record->status === PlanChangeRequestStatus::Pending)
                    ->action(fn (PlanChangeRequest $record) => static::updateAsTenant($record, PlanChangeRequestStatus::Rejected)),
            ]);
    }

    /**
     * This resource lives in the Platform panel (central domain, no resolved
     * tenant) and, by design, reviews plan-change requests across every
     * tenant. That is a deliberate, verified cross-tenant read — the
     * EnsureCentralDomain middleware guarantees this panel only runs on the
     * central domain — so it opts out of the tenant scope explicitly here
     * rather than relying on an unresolved tenant() silently doing the same.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope('tenant');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlanChangeRequests::route('/')];
    }

    /**
     * Mutating this record requires the tenant scope (PlanChangeRequest is
     * tenant-scoped), but this action runs on the central domain with no
     * resolved tenant. The record itself names exactly one tenant, so we act
     * as that tenant for the duration of the write, then clear it — the same
     * pattern used by console commands that iterate tenants one at a time.
     */
    private static function updateAsTenant(PlanChangeRequest $record, PlanChangeRequestStatus $status): void
    {
        $tenancy = app(Tenancy::class);
        $tenancy->set($record->tenant);

        try {
            $record->update(['status' => $status]);
        } finally {
            $tenancy->set(null);
        }
    }
}