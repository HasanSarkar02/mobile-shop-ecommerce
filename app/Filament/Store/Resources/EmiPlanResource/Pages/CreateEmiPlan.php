<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\EmiPlanResource\Pages;

use App\Filament\Store\Resources\EmiPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmiPlan extends CreateRecord
{
    protected static string $resource = EmiPlanResource::class;
}