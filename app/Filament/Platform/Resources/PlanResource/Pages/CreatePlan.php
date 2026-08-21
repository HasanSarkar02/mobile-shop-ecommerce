<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PlanResource\Pages;

use App\Filament\Platform\Resources\PlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;
}
