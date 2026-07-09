<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\EmiPlanResource\Pages;

use App\Filament\Store\Resources\EmiPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmiPlan extends EditRecord
{
    protected static string $resource = EmiPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}