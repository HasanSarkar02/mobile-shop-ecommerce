<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\BannerResource\Pages;

use App\Filament\Store\Resources\BannerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}