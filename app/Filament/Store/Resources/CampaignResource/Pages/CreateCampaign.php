<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CampaignResource\Pages;

use App\Filament\Store\Resources\CampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;
}