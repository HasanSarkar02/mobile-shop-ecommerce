<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\FaqResource\Pages;

use App\Filament\Store\Resources\FaqResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaq extends CreateRecord
{
    protected static string $resource = FaqResource::class;
}