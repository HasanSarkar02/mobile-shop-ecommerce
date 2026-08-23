<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Support\ProductFilterState;
use Illuminate\Http\Request;

class FilterQueryParser
{
    public function fromRequest(Request $request): ProductFilterState
    {
        return new ProductFilterState(
            brandIds: array_map('intval', (array) $request->query('brand', [])),
            priceMin: ProductFilterState::priceFromInput($request->query('price_min')),
            priceMax: ProductFilterState::priceFromInput($request->query('price_max')),
            inStockOnly: $request->boolean('in_stock'),
            emiOnly: $request->boolean('emi'),
            warrantyOnly: $request->boolean('warranty'),
            onSaleOnly: $request->boolean('on_sale'),
            newArrivalOnly: $request->boolean('new_arrival'),
            officialOnly: $request->boolean('official'),
            collectionIds: array_map('intval', (array) $request->query('collection', [])),
            attributes: $this->parseAttributes((array) $request->query('attr', [])),
            sort: (string) $request->query('sort', 'featured'),
            page: max(1, (int) $request->query('page', 1)),
            searchTerm: $request->query('q'),
        );
    }

    private function parseAttributes(array $raw): array
    {
        $parsed = [];

        foreach ($raw as $code => $values) {
            $parsed[$code] = array_values(array_filter(is_array($values) ? $values : explode(',', (string) $values)));
        }

        return array_filter($parsed);
    }
}
