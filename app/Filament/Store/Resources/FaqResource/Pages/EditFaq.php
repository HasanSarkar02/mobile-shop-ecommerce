<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\FaqResource\Pages;

use App\Filament\Store\Resources\FaqResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
