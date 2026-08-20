<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantSubscriptionResource\Pages;

use App\Filament\Platform\Resources\TenantSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListTenantSubscriptions extends ListRecords
{
    protected static string $resource = TenantSubscriptionResource::class;
}
