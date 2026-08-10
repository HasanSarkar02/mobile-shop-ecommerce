<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\BlogPostResource\Pages;

use App\Filament\Store\Resources\BlogPostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}