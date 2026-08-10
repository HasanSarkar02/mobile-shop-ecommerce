<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\FaqResource\Pages;

use App\Filament\Store\Resources\FaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaqs extends ListRecords
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}