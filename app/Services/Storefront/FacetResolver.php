<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\AttributeDefinition;
use App\Models\Brand;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Computes facet options from the current candidate product set.
 * Dynamic attribute facets are entirely metadata-driven off AttributeDefinition
 * (is_filterable=true) — no attribute code is ever hardcoded here.
 */
class FacetResolver
{
    public function resolve(Builder $baseQuery): array
    {
        $productIds = (clone $baseQuery)->pluck('products.id');

        $brands = Brand::query()
            ->whereIn('id', function ($q) use ($productIds): void {
                $q->select('brand_id')->from('products')->whereIn('id', $productIds)->whereNotNull('brand_id');
            })
            ->withCount(['products' => fn (Builder $q) => $q->whereIn('id', $productIds)])
            ->get(['id', 'name']);

        $priceRange = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $attributeFacets = [];

        foreach (AttributeDefinition::query()->where('is_filterable', true)->get() as $definition) {
            $options = ProductAttributeValue::query()
                ->where('attribute_definition_id', $definition->id)
                ->whereIn('product_id', $productIds)
                ->whereNull('product_variant_id')
                ->with('attributeOption')
                ->get()
                ->groupBy(fn (ProductAttributeValue $value) => $value->displayValue())
                ->filter(fn ($group, $label) => filled($label))
                ->map(fn ($group, $label) => ['value' => $label, 'count' => $group->count()])
                ->values();

            if ($options->isNotEmpty()) {
                $attributeFacets[$definition->code] = [
                    'label' => $definition->label,
                    'options' => $options,
                ];
            }
        }

        return [
            'brands' => $brands,
            'price_range' => $priceRange,
            'attributes' => $attributeFacets,
        ];
    }
}