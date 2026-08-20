<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\PlanChangeRequestStatus;
use App\Filament\Platform\Resources\PlanChangeRequestResource\Pages;
use App\Models\PlanChangeRequest;
use App\Models\User;
use App\Services\SubscriptionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('requestedPlan.name')->label('Requested Plan'),
                TextColumn::make('tenant.subscription.plan.name')
                    ->label('Current Plan')
                    ->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime(),
                TextColumn::make('reviewed_at')->dateTime()->placeholder('—'),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('—'),
                TextColumn::make('rejection_reason')->limit(40)->placeholder('—'),
                TextColumn::make('note')->limit(30)->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(fn (): array => collect(PlanChangeRequestStatus::cases())
                        ->mapWithKeys(fn ($status): array => [$status->value => $status->label()])
                        ->all()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->visible(fn (PlanChangeRequest $record): bool => self::isPending($record))
                    ->requiresConfirmation()
                    ->action(function (PlanChangeRequest $record): void {
                        $actor = auth('platform')->user();

                        app(SubscriptionService::class)->approvePlanChange(
                            $record,
                            $actor instanceof User ? $actor : null,
                        );
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (PlanChangeRequest $record): bool => self::isPending($record))
                    ->form([
                        TextInput::make('reason')
                            ->label('Rejection reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, PlanChangeRequest $record): void {
                        $actor = auth('platform')->user();

                        app(SubscriptionService::class)->rejectPlanChange(
                            $record,
                            $actor instanceof User ? $actor : null,
                            reason: (string) ($data['reason'] ?? ''),
                        );
                    }),
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
        return parent::getEloquentQuery()
            ->withoutGlobalScope('tenant')
            ->with(['requestedPlan', 'tenant.subscription.plan', 'reviewer']);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlanChangeRequests::route('/')];
    }

    private static function isPending(PlanChangeRequest $record): bool
    {
        $status = $record->getAttribute('status');

        return $status instanceof PlanChangeRequestStatus
            && $status === PlanChangeRequestStatus::Pending;
    }
}
