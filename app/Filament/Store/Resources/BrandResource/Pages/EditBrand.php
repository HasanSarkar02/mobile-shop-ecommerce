<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\BrandResource\Pages;

use App\Filament\Store\Resources\BrandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}