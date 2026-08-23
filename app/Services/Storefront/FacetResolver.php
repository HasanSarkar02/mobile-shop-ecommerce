<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\AttributeDefinition;
use App\Models\Brand;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Computes facet options from the current candidate product set.
 * Dynamic attribute facets are entirely metadata-driven off AttributeDefinition
 * (is_filterable=true) — no attribute code is ever hardcoded here.
 *
 * Standard faceted-search semantics: each facet is computed over the candidate
 * set with every OTHER active filter applied but its OWN dimension excluded, so
 * selecting one brand keeps the remaining brands selectable. The caller supplies
 * `$candidatesFor(string $dimension)` returning that per-dimension query
 * ('brand' | 'attr:{code}'); without it, every facet falls back to the fully
 * filtered query (legacy behaviour).
 */
class FacetResolver
{
    public function resolve(Builder $filteredQuery, ?\Closure $candidatesFor = null): array
    {
        $candidates = $candidatesFor ?? fn (string $dimension): Builder => $filteredQuery;

        $productIdsFor = fn (string $dimension): Collection => $candidates($dimension)->pluck('products.id');

        $brands = Brand::query()
            ->whereIn('id', function ($q) use ($productIdsFor): void {
                $q->select('brand_id')->from('products')->whereIn('id', $productIdsFor('brand'))->whereNotNull('brand_id');
            })
            ->withCount(['products' => fn (Builder $q) => $q->whereIn('id', $productIdsFor('brand'))])
            ->orderBy('name')
            ->get(['id', 'name']);

        $priceRange = ProductVariant::query()
            ->whereIn('product_id', $candidates('price')->pluck('products.id'))
            ->where('is_active', true)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $attributeFacets = [];

        foreach (AttributeDefinition::query()->where('is_filterable', true)->get() as $definition) {
            $options = ProductAttributeValue::query()
                ->where('attribute_definition_id', $definition->id)
                ->whereIn('product_id', $productIdsFor('attr:'.$definition->code))
                ->with('attributeOption')
                ->get()
                ->groupBy(fn (ProductAttributeValue $value) => $value->displayValue())
                ->filter(fn ($group, $label) => filled($label))
                // Count distinct products per option so variant-scoped values
                // (one row per variant) do not inflate the facet count.
                ->map(fn ($group, $label) => ['value' => $label, 'count' => $group->pluck('product_id')->unique()->count()])
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
