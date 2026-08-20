<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\DomainResource\Pages;

use App\Enums\DomainStatus;
use App\Filament\Platform\Resources\DomainResource;
use App\Models\Domain;
use App\Models\User;
use App\Services\DomainDnsVerificationService;
use App\Services\DomainManagementService;
use App\Support\Tenancy\DomainVerificationChallenge;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewDomain extends ViewRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateChallenge')
                ->label('Regenerate Challenge')
                ->requiresConfirmation()
                ->visible(fn (Domain $record): bool => in_array(self::statusOf($record), [DomainStatus::Pending, DomainStatus::Verified, DomainStatus::Failed], true))
                ->action(function (Domain $record): void {
                    try {
                        $challenge = app(DomainManagementService::class)->regenerateVerificationChallenge($record, $this->actor());
                        self::rememberChallenge($challenge);
                        $this->success('Verification challenge regenerated.', self::challengeInstructions($challenge));
                    } catch (Throwable $exception) {
                        $this->failure('Challenge regeneration failed.', $exception->getMessage());
                    }
                }),
            Action::make('checkDns')
                ->label('Check DNS Now')
                ->visible(fn (Domain $record): bool => self::statusOf($record) === DomainStatus::Pending)
                ->action(function (Domain $record): void {
                    try {
                        $queued = app(DomainDnsVerificationService::class)->dispatchCheck($record);

                        if (! $queued) {
                            $this->failure('DNS check was not queued.', 'The domain may be expired, already queued, or no longer pending.');

                            return;
                        }

                        $this->success('DNS verification check queued.', 'The domain status will update after the queued DNS check completes.');
                    } catch (Throwable $exception) {
                        $this->failure('DNS check could not be queued.', $exception->getMessage());
                    }
                }),
            Action::make('activate')
                ->label('Activate')
                ->requiresConfirmation()
                ->visible(fn (Domain $record): bool => in_array(self::statusOf($record), [DomainStatus::Verified, DomainStatus::Suspended], true))
                ->action(function (Domain $record): void {
                    $this->runAction('Domain activated.', fn () => app(DomainManagementService::class)->activate($record, $this->actor()));
                }),
            Action::make('setPrimary')
                ->label('Set Primary')
                ->requiresConfirmation()
                ->visible(fn (Domain $record): bool => self::statusOf($record) === DomainStatus::Active && ! DomainResource::isPrimaryForUi($record))
                ->action(function (Domain $record): void {
                    $this->runAction('Primary domain updated.', fn () => app(DomainManagementService::class)->setPrimary($record, $this->actor()));
                }),
            Action::make('clearPrimary')
                ->label('Clear Primary')
                ->requiresConfirmation()
                ->visible(fn (Domain $record): bool => DomainResource::isPrimaryForUi($record))
                ->action(function (Domain $record): void {
                    $this->runAction('Primary domain cleared. The tenant subdomain is the fallback.', fn () => app(DomainManagementService::class)->clearPrimaryDomain($record, $this->actor()));
                }),
            Action::make('suspend')
                ->label('Suspend')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Domain $record): bool => self::statusOf($record) === DomainStatus::Active)
                ->action(function (Domain $record): void {
                    $this->runAction('Domain suspended.', fn () => app(DomainManagementService::class)->suspend($record, $this->actor()));
                }),
            Action::make('revoke')
                ->label('Revoke')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('Reason')->rows(3)])
                ->visible(fn (Domain $record): bool => self::statusOf($record) !== DomainStatus::Revoked)
                ->action(function (Domain $record, array $data): void {
                    $this->runAction('Domain revoked.', fn () => app(DomainManagementService::class)->revoke($record, $this->actor(), $data['reason'] ?? null));
                }),
            Action::make('removePending')
                ->label('Remove Pending Domain')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Domain $record): bool => in_array(self::statusOf($record), [DomainStatus::Pending, DomainStatus::Failed], true))
                ->action(function (Domain $record): void {
                    try {
                        app(DomainManagementService::class)->removePendingDomain($record, $this->actor());
                        Notification::make()->success()->title('Pending domain removed.')->send();
                        $this->redirect(DomainResource::getUrl('index'));
                    } catch (Throwable $exception) {
                        $this->failure('Domain removal failed.', $exception->getMessage());
                    }
                }),
        ];
    }

    public static function rememberChallenge(DomainVerificationChallenge $challenge): void
    {
        session()->put(DomainResource::challengeSessionKey($challenge->domain->id), [
            'domain_id' => $challenge->domain->id,
            'record_name' => $challenge->recordName,
            'record_value' => $challenge->recordValue,
            'expires_at' => $challenge->expiresAt->toDateTimeString(),
        ]);
    }

    private static function statusOf(Domain $record): DomainStatus
    {
        $status = $record->getAttribute('status');

        if ($status instanceof DomainStatus) {
            return $status;
        }

        return DomainStatus::from((string) $status);
    }

    private static function challengeInstructions(DomainVerificationChallenge $challenge): string
    {
        return 'Add TXT '.$challenge->recordName.' = '.$challenge->recordValue
            .' before '.$challenge->expiresAt->toDateTimeString().'.';
    }

    private function actor(): User
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function runAction(string $title, callable $callback): void
    {
        try {
            $callback();
            Notification::make()->success()->title($title)->send();
        } catch (Throwable $exception) {
            $this->failure('Domain action failed.', $exception->getMessage());
        }
    }

    private function success(string $title, ?string $body = null): void
    {
        $notification = Notification::make()->success()->title($title);

        if ($body !== null) {
            $notification->body($body);
        }

        $notification->send();
    }

    private function failure(string $title, string $body): void
    {
        Notification::make()->danger()->title($title)->body($body)->send();
    }
}
