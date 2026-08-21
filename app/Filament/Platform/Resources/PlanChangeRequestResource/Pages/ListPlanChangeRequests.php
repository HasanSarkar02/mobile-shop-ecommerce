<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PlanChangeRequestResource\Pages;

use App\Filament\Platform\Resources\PlanChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListPlanChangeRequests extends ListRecords
{
    protected static string $resource = PlanChangeRequestResource::class;
}
