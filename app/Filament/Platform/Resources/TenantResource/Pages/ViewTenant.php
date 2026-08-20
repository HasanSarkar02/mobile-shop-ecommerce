<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Enums\DeploymentMode;
use App\Filament\Platform\Resources\TenantResource;
use App\Http\Middleware\ResolveSupportSession;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Services\OwnerInvitationService;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Throwable;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

                    session()->put(ResolveSupportSession::SESSION_KEY, [
                        'id' => $uuid,
                        'tenant_id' => (int) $record->getKey(),
                        'started_at' => now()->toDateTimeString(),
                        'expires_at' => now()->addMinutes(ResolveSupportSession::IDLE_TTL_MINUTES)->toDateTimeString(),
                        'entered_by_user_id' => (int) $admin->getKey(),
                        'reason' => $data['reason'],
                        'is_write_enabled' => $data['is_write_enabled'],
                    ]);

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

                    $this->redirect(url('/support/'.$record->getKey().'/admin'));
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
