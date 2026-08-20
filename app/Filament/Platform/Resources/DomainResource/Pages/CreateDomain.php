<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\DomainResource\Pages;

use App\Filament\Platform\Resources\DomainResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DomainManagementService;
use App\Support\Tenancy\DomainVerificationChallenge;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDomain extends CreateRecord
{
    protected static string $resource = DomainResource::class;

    protected ?string $challengeRecordName = null;

    protected ?string $challengeRecordValue = null;

    protected ?string $challengeExpiresAt = null;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Tenant::query()->findOrFail((int) $data['tenant_id']);
        $actor = auth('platform')->user();

        abort_unless($actor instanceof User, 403);

        $challenge = app(DomainManagementService::class)
            ->createPendingDomain($tenant, (string) $data['domain'], $actor);

        $this->rememberChallenge($challenge);

        return $challenge->domain;
    }

    protected function getCreatedNotification(): ?Notification
    {
        if ($this->challengeRecordName === null || $this->challengeRecordValue === null) {
            return parent::getCreatedNotification();
        }

        return Notification::make()
            ->success()
            ->title('Pending domain created')
            ->body($this->challengeInstructions());
    }

    protected function getRedirectUrl(): string
    {
        return DomainResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    private function rememberChallenge(DomainVerificationChallenge $challenge): void
    {
        $this->challengeRecordName = $challenge->recordName;
        $this->challengeRecordValue = $challenge->recordValue;
        $this->challengeExpiresAt = $challenge->expiresAt->toDateTimeString();

        session()->put(DomainResource::challengeSessionKey($challenge->domain->id), [
            'domain_id' => $challenge->domain->id,
            'record_name' => $challenge->recordName,
            'record_value' => $challenge->recordValue,
            'expires_at' => $challenge->expiresAt->toDateTimeString(),
        ]);
    }

    private function challengeInstructions(): string
    {
        return 'Add a TXT record named '.$this->challengeRecordName
            .' with value '.$this->challengeRecordValue
            .' before '.$this->challengeExpiresAt.'.';
    }
}
