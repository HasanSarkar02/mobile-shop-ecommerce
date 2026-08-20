<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\FulfillmentStrategy;
use App\Enums\VariantAvailability;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CompareService;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CompareController extends Controller
{
    public function show(CompareService $compare, TenantUrlGenerator $urls)
    {
        $ids = $compare->ids();

        $products = Product::query()
            ->whereIn('id', $ids)
            ->published()
            ->with([
                'translations',
                'brand',
                'media',
                'attributeValues.attributeDefinition',
                'attributeValues.attributeOption',
                'variants.attributeValues.attributeDefinition',
                'variants.attributeValues.attributeOption',
            ])
            ->get();

        // Drop session ids that no longer resolve to a published product so the
        // page count, header badge, and comparison table stay in sync.
        $compare->prune($products->pluck('id')->all());

        // Preserve the order in which the customer added the products.
        $ordered = $ids !== []
            ? $products->keyBy('id')->only($ids)->values()
            : $products;

        return view('storefront.compare.show', [
            'productsJson' => $ordered->map(fn (Product $product) => $this->toJson($product, $urls))->values(),
            'rowsJson' => $this->buildSpecRows($ordered),
            'compareCount' => count($ids),
            'compareLimit' => $compare->limit(),
        ]);
    }

    public function toggle(Request $request, CompareService $compare): JsonResponse|RedirectResponse
    {
        try {
            $added = $compare->toggle((int) $request->input('product_id'));
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['added' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $message = $added ? 'Added to compare.' : 'Removed from compare.';

        if ($request->wantsJson()) {
            return response()->json(['added' => $added, 'message' => $message]);
        }

        return back()->with('status', $message);
    }

    public function remove(Request $request, CompareService $compare): JsonResponse|RedirectResponse
    {
        $compare->remove((int) $request->input('product_id'));

        return $this->jsonResponse($request, 'Product removed from compare.');
    }

    public function clear(Request $request, CompareService $compare): JsonResponse|RedirectResponse
    {
        $compare->clear();

        return $this->jsonResponse($request, 'Comparison list cleared.');
    }

    private function jsonResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    /**
     * Build the compact view-model each product needs for its comparison
     * column, derived from its active variants (never hard-coded specs).
     */
    private function toJson(Product $product, TenantUrlGenerator $urls): array
    {
        $activeVariants = $product->variants->where('is_active', true)->values();
        $cheapest = $activeVariants->sortBy('price')->first();

        $inStock = $activeVariants->contains(fn ($v) => $v->availability === VariantAvailability::InStock);
        $preorder = $activeVariants->contains(fn ($v) => $v->fulfillment_strategy === FulfillmentStrategy::Preorder);
        $dropship = $activeVariants->contains(fn ($v) => $v->fulfillment_strategy === FulfillmentStrategy::Dropship);

        $status = match (true) {
            $inStock => ['label' => 'In Stock', 'class' => 'text-green-600 dark:text-green-400'],
            $preorder => ['label' => 'Pre-Order', 'class' => 'text-amber-600 dark:text-amber-400'],
            $dropship => ['label' => 'Available', 'class' => 'text-gray-500 dark:text-gray-400'],
            default => ['label' => 'Out of Stock', 'class' => 'text-red-600 dark:text-red-400'],
        };

        $price = $cheapest?->price;
        $compareAt = $cheapest?->compare_at_price;
        $discount = $price && $compareAt && $compareAt > $price
            ? (int) round((($compareAt - $price) / $compareAt) * 100)
            : null;

        $firstActiveAvailable = $activeVariants->first(fn ($v) => $v->availability !== VariantAvailability::OutOfStock);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'url' => $urls->canonicalRoute(tenant(), 'storefront.product', [$product->translation('en')?->slug ?? $product->id]),
            'image' => $product->getFirstMediaUrl('images', 'thumb') ?: null,
            'brand' => $product->brand?->name,
            'price' => $price !== null ? '৳'.number_format($price / 100) : null,
            'compareAt' => $compareAt && $compareAt > $price ? '৳'.number_format($compareAt / 100) : null,
            'discount' => $discount,
            'statusLabel' => $status['label'],
            'statusClass' => $status['class'],
            'variantCount' => $activeVariants->count(),
            'addableVariantId' => $activeVariants->count() === 1 && $firstActiveAvailable !== null
                ? $activeVariants->first()->id
                : null,
        ];
    }

    /**
     * Aggregate product-level (and representative-variant-level) attribute
     * values into rows keyed by attribute code, sorted by the definition's
     * sort order, each holding the raw display value per compared product.
     */
    private function buildSpecRows(Collection $products): array
    {
        $rows = [];

        foreach ($products as $product) {
            $cheapest = $product->variants->where('is_active', true)->sortBy('price')->first();

            $values = $product->attributeValues
                ->concat($cheapest ? $cheapest->attributeValues : collect())
                ->filter(fn ($value) => $value->attributeDefinition !== null);

            foreach ($values as $value) {
                $definition = $value->attributeDefinition;

                $row = $rows[$definition->code] ?? [
                    'label' => $definition->label,
                    'sort_order' => $definition->sort_order ?? PHP_INT_MAX,
                    'values' => [],
                ];

                $row['values'][$product->id] = $value->displayValue() ?? '—';

                $rows[$definition->code] = $row;
            }
        }

        uasort($rows, fn ($a, $b) => [$a['sort_order'], $a['label']] <=> [$b['sort_order'], $b['label']]);

        return array_values(array_map(
            fn ($row) => ['label' => $row['label'], 'values' => $row['values']],
            $rows,
        ));
    }
}
