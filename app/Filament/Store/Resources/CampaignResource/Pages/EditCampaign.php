<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CampaignResource\Pages;

use App\Filament\Store\Resources\CampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
