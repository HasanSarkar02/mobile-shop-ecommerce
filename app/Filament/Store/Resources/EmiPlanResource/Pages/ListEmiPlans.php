<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\EmiPlanResource\Pages;

use App\Filament\Store\Resources\EmiPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmiPlans extends ListRecords
{
    protected static string $resource = EmiPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}