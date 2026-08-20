<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaffResource\Pages;

use App\Filament\Store\Resources\StaffResource;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! app(SubscriptionService::class)->canAddStaff(tenant())) {
            Notification::make()->title('Staff limit reached for your plan. Please upgrade to add more staff.')->danger()->send();
            $this->halt();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->forceFill(['tenant_id' => tenant()->id, 'role' => 'staff', 'is_active' => true])->save();

        return $user;
    }
}
