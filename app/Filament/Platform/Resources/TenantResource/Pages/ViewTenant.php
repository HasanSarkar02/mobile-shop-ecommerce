<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Enums\DeploymentMode;
use App\Enums\TombstoneReason;
use App\Filament\Platform\Resources\TenantResource;
use App\Http\Middleware\ResolveSupportSession;
use App\Models\SubdomainTombstone;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Services\OwnerInvitationService;
use App\Services\TenantApprovalService;
use App\Services\TenantDeletionService;
use App\Support\Tenancy\TenantUrlGenerator;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewStore')
                ->label('View Store')
                ->icon('heroicon-o-eye')
                ->url(fn (Tenant $record): string => app(TenantUrlGenerator::class)->storefront($record))
                ->openUrlInNewTab()
                ->color('gray')
                ->visible(fn (Tenant $record): bool => $record->isActive()),
            Action::make('approveShop')
                ->label('Approve Shop')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Tenant $record): bool => $record->getAttribute('status') === 'pending')
                ->action(function (Tenant $record): void {
                    try {
                        $actor = auth('platform')->user();
                        abort_unless($actor instanceof User, 403);
                        app(TenantApprovalService::class)->approve($record, $actor);
                        FilamentNotification::make()->success()->title('Shop approved.')->send();
                        $this->redirect(request()->url());
                    } catch (Throwable $exception) {
                        FilamentNotification::make()->danger()->title('Shop could not be approved.')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('rejectShop')
                ->label('Reject Shop')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    TextInput::make('reason')
                        ->label('Reason')
                        ->required()
                        ->minLength(5)
                        ->maxLength(500),
                ])
                ->visible(fn (Tenant $record): bool => $record->getAttribute('status') === 'pending')
                ->action(function (Tenant $record, array $data): void {
                    try {
                        $actor = auth('platform')->user();
                        abort_unless($actor instanceof User, 403);
                        app(TenantApprovalService::class)->reject($record, $actor, $data['reason']);
                        FilamentNotification::make()->success()->title('Shop rejected.')->send();
                        $this->redirect(request()->url());
                    } catch (Throwable $exception) {
                        FilamentNotification::make()->danger()->title('Shop could not be rejected.')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('resendOwnerInvitation')
                ->label('Resend Owner Invitation')
                ->requiresConfirmation()
                ->visible(fn (Tenant $record): bool => $this->ownerInvitation($record) !== null)
                ->action(function (Tenant $record): void {
                    try {
                        $owner = $this->owner($record);
                        $actor = auth('platform')->user();
                        abort_unless($owner instanceof User && $actor instanceof User, 403);
                        $issued = app(OwnerInvitationService::class)->resend($record, $owner, $actor);
                        $expiresAt = $issued['invitation']->getAttribute('expires_at');
                        abort_unless($expiresAt instanceof CarbonInterface, 500);
                        $owner->notify(new TenantOwnerInvitationNotification(
                            $record,
                            $issued['token'],
                            $expiresAt,
                        ));
                        FilamentNotification::make()->success()->title('Owner invitation resent.')->send();
                    } catch (Throwable $exception) {
                        FilamentNotification::make()->danger()->title('Invitation could not be resent.')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('revokeOwnerInvitation')
                ->label('Revoke Owner Invitation')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Tenant $record): bool => $this->ownerInvitation($record) !== null)
                ->action(function (Tenant $record): void {
                    try {
                        $invitation = $this->ownerInvitation($record);
                        $actor = auth('platform')->user();
                        abort_unless($invitation !== null && $actor instanceof User, 403);
                        app(OwnerInvitationService::class)->revoke($invitation, $actor);
                        FilamentNotification::make()->success()->title('Owner invitation revoked.')->send();
                    } catch (Throwable $exception) {
                        FilamentNotification::make()->danger()->title('Invitation could not be revoked.')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('enterSupportMode')
                ->label('Enter Support Mode')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->form([
                    TextInput::make('reason')
                        ->label('Reason for support')
                        ->required()
                        ->minLength(5)
                        ->maxLength(500)
                        ->helperText('Explain why this tenant requires support access.'),
                    Toggle::make('is_write_enabled')
                        ->label('Enable Write Access')
                        ->helperText('Allow POST, PUT, DELETE and other mutating requests while support mode is active.'),
                ])
                ->visible(fn (Tenant $record): bool => $this->canEnterSupportMode($record))
                ->action(function (Tenant $record, array $data): void {
                    abort_unless($this->canEnterSupportMode($record), 403);

                    $admin = auth('platform')->user();
                    abort_unless($admin instanceof User, 403);

                    $uuid = (string) Str::uuid();

                    $payload = [
                        'id' => $uuid,
                        'tenant_id' => (int) $record->getKey(),
                        'started_at' => now()->toDateTimeString(),
                        'expires_at' => now()->addMinutes(ResolveSupportSession::IDLE_TTL_MINUTES)->toDateTimeString(),
                        'entered_by_user_id' => (int) $admin->getKey(),
                        'reason' => $data['reason'],
                        'is_write_enabled' => $data['is_write_enabled'],
                    ];

                    Cache::put('support_magic:'.$uuid, $payload, now()->addMinutes(ResolveSupportSession::IDLE_TTL_MINUTES));

                    activity('support')
                        ->performedOn($record)
                        ->causedBy($admin)
                        ->event('support.mode_started')
                        ->withProperties([
                            'support_session_id' => $uuid,
                            'tenant_id' => (int) $record->getKey(),
                            'entered_by_user_id' => (int) $admin->getKey(),
                            'reason' => $data['reason'],
                            'is_write_enabled' => $data['is_write_enabled'],
                        ])
                        ->log('support.mode_started');

                    $magicUrl = app(TenantUrlGenerator::class)->canonicalPath($record, '/support/magic?token='.$uuid);

                    $this->redirect($magicUrl);
                }),
            Action::make('scheduleDeletion')
                ->label('Schedule Deletion')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->form([
                    Textarea::make('reason')->label('Reason')->required()->minLength(5)->maxLength(500),
                    Select::make('tombstone_reason')->label('Quarantine')->options([
                        TombstoneReason::TrialZeroOrders->value => TombstoneReason::TrialZeroOrders->label(),
                        TombstoneReason::Active30d->value => TombstoneReason::Active30d->label(),
                        TombstoneReason::Abuse->value => TombstoneReason::Abuse->label(),
                    ])->default(TombstoneReason::Active30d->value)->required()->helperText('7-day grace, then quarantine per locked choices.'),
                ])
                ->visible(fn (Tenant $record): bool => in_array($record->status, ['trial', 'active', 'suspended'], true) && ! $record->trashed() && ! $record->isPendingDeletion())
                ->action(function (Tenant $record, array $data): void {
                    $actor = auth('platform')->user();
                    abort_unless($actor instanceof User, 403);
                    $reasonEnum = TombstoneReason::tryFrom($data['tombstone_reason']) ?? TombstoneReason::Active30d;
                    app(TenantDeletionService::class)->requestDeletion($record, $actor, $data['reason'], $reasonEnum);
                    FilamentNotification::make()->success()->title('Deletion scheduled (7-day grace).')->send();
                    $this->redirect(request()->url());
                }),
            Action::make('cancelDeletion')
                ->label('Cancel Deletion')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (Tenant $record): bool => $record->isPendingDeletion() && ! $record->trashed())
                ->action(function (Tenant $record): void {
                    $actor = auth('platform')->user();
                    abort_unless($actor instanceof User, 403);
                    app(TenantDeletionService::class)->cancelDeletion($record, $actor);
                    FilamentNotification::make()->success()->title('Deletion cancelled.')->send();
                    $this->redirect(request()->url());
                }),
            Action::make('softDeleteNow')
                ->label('Soft Delete Now')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Select::make('reason')->label('Quarantine')->options([
                        TombstoneReason::TrialZeroOrders->value => TombstoneReason::TrialZeroOrders->label(),
                        TombstoneReason::Active30d->value => TombstoneReason::Active30d->label(),
                        TombstoneReason::Abuse->value => TombstoneReason::Abuse->label(),
                    ])->default(TombstoneReason::Active30d->value)->required(),
                ])
                ->visible(fn (Tenant $record): bool => ! $record->trashed() && ! $record->isPendingDeletion() && in_array($record->status, ['trial', 'active', 'suspended', 'rejected'], true))
                ->action(function (Tenant $record, array $data): void {
                    $actor = auth('platform')->user();
                    abort_unless($actor instanceof User, 403);
                    $reason = TombstoneReason::tryFrom($data['reason']) ?? TombstoneReason::Active30d;
                    $force = $reason === TombstoneReason::Abuse;
                    app(TenantDeletionService::class)->softDelete($record, $actor, $reason, $force);
                    FilamentNotification::make()->success()->title('Tenant soft-deleted, subdomain tombstoned.')->send();
                    $this->redirect(route('filament.platform.resources.tenants.index'));
                }),
            Action::make('restoreTenant')
                ->label('Restore')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Tenant $record): bool => $record->trashed())
                ->action(function (Tenant $record): void {
                    $original = $record->subdomain_original;
                    $record->restore();
                    $restoreData = ['status' => 'suspended', 'deletion_requested_at' => null, 'deletion_scheduled_at' => null, 'deletion_reason' => null, 'deleted_by_id' => null];
                    if (filled($original)) {
                        // Check if original subdomain is still available (not taken, not quarantined)
                        $taken = Tenant::withTrashed()->where('subdomain', strtolower($original))->where('id', '!=', $record->id)->exists();
                        $tombCheck = SubdomainTombstone::where('subdomain', strtolower($original))->where('tenant_id', '!=', $record->id)->where(function ($q) {
                            $q->whereNull('quarantine_until')->orWhere('quarantine_until', '>', now());
                        })->exists();
                        // Only restore original subdomain if not taken/quarantined by another tenant's tombstone
                        $ownTomb = SubdomainTombstone::where('tenant_id', $record->id)->where('subdomain', strtolower($original))->first();
                        $isOwnTombPermanent = $ownTomb && $ownTomb->isPermanent();
                        if (! $taken && ! $tombCheck && ! $isOwnTombPermanent) {
                            $restoreData['subdomain'] = strtolower($original);
                            $restoreData['subdomain_original'] = null;
                        }
                    }
                    $record->forceFill($restoreData)->save();
                    // Remove own tombstone if not permanent
                    $tomb = SubdomainTombstone::where('tenant_id', $record->id)->first();
                    if ($tomb && ! $tomb->isPermanent()) {
                        $tomb->delete();
                    }
                    // Also clean up any tombstone for original subdomain that belongs to this tenant
                    if (filled($original)) {
                        $tomb2 = SubdomainTombstone::where('tenant_id', $record->id)->where('subdomain', strtolower($original))->first();
                        if ($tomb2 && ! $tomb2->isPermanent() && $tomb2->id !== ($tomb?->id)) {
                            $tomb2->delete();
                        }
                    }
                    FilamentNotification::make()->success()->title('Tenant restored.')->send();
                    $this->redirect(request()->url());
                }),
            Action::make('forcePurge')
                ->label('Force Purge (Hard Delete)')
                ->icon('heroicon-o-fire')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Permanently purge tenant?')
                ->modalDescription('This will queue async purge of all tenant data. Tombstone survives for quarantine. Platform only.')
                ->form([
                    Select::make('reason')->label('Tombstone')->options([
                        TombstoneReason::Active30d->value => TombstoneReason::Active30d->label(),
                        TombstoneReason::Abuse->value => TombstoneReason::Abuse->label(),
                    ])->default(TombstoneReason::Active30d->value)->required(),
                ])
                ->visible(fn (Tenant $record): bool => $record->trashed())
                ->action(function (Tenant $record, array $data): void {
                    $actor = auth('platform')->user();
                    abort_unless($actor instanceof User && $actor->is_platform_admin, 403);
                    $reason = TombstoneReason::tryFrom($data['reason']) ?? TombstoneReason::Active30d;
                    app(TenantDeletionService::class)->purge($record, $actor, $reason);
                    FilamentNotification::make()->success()->title('Purge queued.')->send();
                    $this->redirect(route('filament.platform.resources.tenants.index'));
                }),
        ];
    }

    private function owner(Tenant $tenant): ?User
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->orderBy('id')
            ->first();
    }

    private function ownerInvitation(Tenant $tenant): ?TenantInvitation
    {
        $owner = $this->owner($tenant);

        return $owner === null ? null : app(OwnerInvitationService::class)->latestFor($tenant, $owner);
    }

    private function canEnterSupportMode(Tenant $record): bool
    {
        if (config('deployment.mode') !== DeploymentMode::SaaS->value) {
            return false;
        }

        $admin = auth('platform')->user();

        if (! $admin instanceof User) {
            return false;
        }

        if ($admin->getAttribute('is_platform_admin') !== true || $admin->getAttribute('is_active') !== true) {
            return false;
        }

        if (! $record->isActive()) {
            return false;
        }

        return ! session()->has(ResolveSupportSession::SESSION_KEY);
    }
}
