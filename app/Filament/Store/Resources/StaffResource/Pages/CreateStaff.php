<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaffResource\Pages;

use App\Filament\Store\Resources\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\SubscriptionService;
use Filament\Notifications\Notification;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! app(SubscriptionService::class)->canAddStaff(tenant())) {
            Notification::make()->title('Staff limit reached for your plan. Please upgrade to add more staff.')->danger()->send();
            $this->halt();
        }
        $data['tenant_id'] = tenant()->id;
        $data['role'] = 'staff';

        return $data;
    }
}