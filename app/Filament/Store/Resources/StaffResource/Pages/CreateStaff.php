<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StaffResource\Pages;

use App\Filament\Store\Resources\StaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant()->id;
        $data['role'] = 'staff';

        return $data;
    }
}