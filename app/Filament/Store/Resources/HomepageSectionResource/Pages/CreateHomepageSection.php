<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\HomepageSectionResource\Pages;

use App\Filament\Store\Resources\HomepageSectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomepageSection extends CreateRecord
{
    protected static string $resource = HomepageSectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->packConfig($data);
    }

    private function packConfig(array $data): array
    {
        $data['config'] = array_filter([
            'placement' => $data['config_placement'] ?? null,
            'layout' => $data['config_layout'] ?? null,
            'data_source' => $data['config_data_source'] ?? null,
            'limit' => $data['config_limit'] ?? null,
            'category_id' => $data['config_category_id'] ?? null,
            'collection_id' => $data['config_collection_id'] ?? null,
            'tag_id' => $data['config_tag_id'] ?? null,
            'source' => $data['config_source'] ?? null,
            'category_ids' => $data['config_category_ids'] ?? null,
            'brand_ids' => $data['config_brand_ids'] ?? null,
            'html' => $data['config_html'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        unset(
            $data['config_placement'], $data['config_layout'], $data['config_data_source'], $data['config_limit'],
            $data['config_category_id'], $data['config_collection_id'], $data['config_tag_id'],
            $data['config_source'], $data['config_category_ids'], $data['config_brand_ids'], $data['config_html'],
        );

        return $data;
    }
}