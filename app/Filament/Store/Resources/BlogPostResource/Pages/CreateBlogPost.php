<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\BlogPostResource\Pages;

use App\Filament\Store\Resources\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = auth()->id();

        return $data;
    }
}