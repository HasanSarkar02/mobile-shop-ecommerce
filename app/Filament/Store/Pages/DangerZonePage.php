<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Services\TenantDeletionService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class DangerZonePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Danger Zone';

    protected static ?int $navigationSort = 9;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Danger Zone';

    protected string $view = 'filament.store.pages.danger-zone';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public string $reason = '';

    public function requestDeletion(): void
    {
        $tenant = tenant();
        if (! $tenant) {
            Notification::make()->title('No tenant context.')->danger()->send();

            return;
        }

        $this->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            app(TenantDeletionService::class)->requestDeletion($tenant, auth()->user(), $this->reason);

            $this->reason = '';

            Notification::make()->title('Deletion scheduled — 7-day grace period. You can cancel anytime.')->success()->send();
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function cancelDeletion(): void
    {
        $tenant = tenant();
        if (! $tenant) {
            return;
        }

        try {
            app(TenantDeletionService::class)->cancelDeletion($tenant, auth()->user());
            Notification::make()->title('Deletion cancelled.')->success()->send();
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function getTenantStatus(): string
    {
        return (string) (tenant()?->status ?? 'unknown');
    }

    public function isPendingDeletion(): bool
    {
        return tenant()?->isPendingDeletion() ?? false;
    }
}
