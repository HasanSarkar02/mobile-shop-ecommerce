<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantSubscriptionResource\Pages;

use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\TenantSubscriptionResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewTenantSubscription extends ViewRecord
{
    protected static string $resource = TenantSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignPlan')
                ->label('Assign Plan')
                ->schema([
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
                        ->required(),
                    Textarea::make('note')->label('Note')->rows(3),
                ])
                ->action(function (TenantSubscription $record, array $data): void {
                    $this->runAction('Plan assigned.', function () use ($record, $data): void {
                        $plan = Plan::query()->find((int) $data['plan_id']);
                        abort_unless($plan instanceof Plan, 403);

                        app(SubscriptionService::class)->assignPlan(
                            $this->tenant($record),
                            $plan,
                            $this->actor(),
                            $this->note($data),
                        );
                    });
                }),
            Action::make('extendSubscription')
                ->label('Extend')
                ->visible(fn (TenantSubscription $record): bool => ! self::isTerminal($record))
                ->schema([
                    TextInput::make('days')
                        ->label('Days to extend')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->integer(),
                    Textarea::make('note')->label('Note')->rows(3),
                ])
                ->action(function (TenantSubscription $record, array $data): void {
                    $this->runAction('Subscription extended.', fn () => app(SubscriptionService::class)->extendSubscription(
                        $this->tenant($record),
                        (int) $data['days'],
                        $this->actor(),
                        $this->note($data),
                    ));
                }),
            Action::make('cancelSubscription')
                ->label('Cancel')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (TenantSubscription $record): bool => self::statusOf($record) !== SubscriptionStatus::Cancelled)
                ->schema([Textarea::make('note')->label('Note')->rows(3)])
                ->action(function (TenantSubscription $record, array $data): void {
                    $this->runAction('Subscription cancelled.', fn () => app(SubscriptionService::class)->cancelSubscription(
                        $this->tenant($record),
                        $this->actor(),
                        $this->note($data),
                    ));
                }),
            Action::make('reactivateSubscription')
                ->label('Reactivate')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (TenantSubscription $record): bool => in_array(self::statusOf($record), [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true))
                ->schema([Textarea::make('note')->label('Note')->rows(3)])
                ->action(function (TenantSubscription $record, array $data): void {
                    $this->runAction('Subscription reactivated.', fn () => app(SubscriptionService::class)->reactivateSubscription(
                        $this->tenant($record),
                        $this->actor(),
                        $this->note($data),
                    ));
                }),
        ];
    }

    private static function statusOf(TenantSubscription $record): ?SubscriptionStatus
    {
        $status = $record->getAttribute('status');

        return $status instanceof SubscriptionStatus ? $status : SubscriptionStatus::tryFrom((string) $status);
    }

    private static function isTerminal(TenantSubscription $record): bool
    {
        return in_array(self::statusOf($record), [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true);
    }

    private function tenant(TenantSubscription $record): Tenant
    {
        $tenant = $record->tenant;
        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function actor(): User
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function note(array $data): ?string
    {
        $note = $data['note'] ?? null;

        return is_string($note) && $note !== '' ? $note : null;
    }

    private function runAction(string $title, callable $callback): void
    {
        try {
            $callback();
            Notification::make()->success()->title($title)->send();
        } catch (Throwable $exception) {
            Notification::make()->danger()->title('Subscription action failed.')->body($exception->getMessage())->send();
        }
    }
}
