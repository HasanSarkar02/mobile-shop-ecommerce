<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\HomepageSectionResource\Pages;

use App\Filament\Store\Resources\HomepageSectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHomepageSection extends EditRecord
{
    protected static string $resource = HomepageSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $config = $data['config'] ?? [];

        $data['config_placement'] = $config['placement'] ?? null;
        $data['config_layout'] = $config['layout'] ?? null;
        $data['config_data_source'] = $config['data_source'] ?? null;
        $data['config_limit'] = $config['limit'] ?? null;
        $data['config_category_id'] = $config['category_id'] ?? null;
        $data['config_collection_id'] = $config['collection_id'] ?? null;
        $data['config_tag_id'] = $config['tag_id'] ?? null;
        $data['config_source'] = $config['source'] ?? null;
        $data['config_category_ids'] = $config['category_ids'] ?? null;
        $data['config_brand_ids'] = $config['brand_ids'] ?? null;
        $data['config_html'] = $config['html'] ?? null;
        $data['config_items'] = $config['items'] ?? null;
        $data['config_presentation_rows'] = $config['presentation']['rows'] ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $presentation = null;
        if (isset($data['config_presentation_rows']) && $data['config_presentation_rows'] !== '' && $data['config_presentation_rows'] !== null) {
            $rows = (int) $data['config_presentation_rows'];
            if (in_array($rows, [1, 2], true)) {
                $presentation = ['rows' => $rows];
            }
        }

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
            'items' => $data['config_items'] ?? null,
            'presentation' => $presentation,
        ], fn ($value) => $value !== null && $value !== '');

        unset(
            $data['config_placement'], $data['config_layout'], $data['config_data_source'], $data['config_limit'],
            $data['config_category_id'], $data['config_collection_id'], $data['config_tag_id'],
            $data['config_source'], $data['config_category_ids'], $data['config_brand_ids'], $data['config_html'], $data['config_items'], $data['config_presentation_rows'],
        );

        return $data;
    }
}
