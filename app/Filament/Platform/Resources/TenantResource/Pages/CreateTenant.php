<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Models\User;
use App\Services\TenantBootstrapService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        [$tenant] = app(TenantBootstrapService::class)->bootstrap([
            'name' => $data['name'],
            'subdomain' => $data['subdomain'],
            'plan' => $data['plan'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'owner' => [
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
            ],
        ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION, invitedBy: $actor);

        return $tenant;
    }
}
