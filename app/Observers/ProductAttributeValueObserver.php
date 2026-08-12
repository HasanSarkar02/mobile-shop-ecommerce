<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AttributeDefinition;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Services\VariantSignatureService;
use Illuminate\Validation\ValidationException;

/**
 * Guards variant combination integrity whenever a variant-scoped,
 * variant-defining attribute value is created or updated:
 *
 *  1. one value per variant-defining attribute per variant (Size, Color, ...),
 *  2. one combination per product (no duplicate SKU dimension sets).
 *
 * Product-level attribute values (product_variant_id = null) and non-defining
 * attributes are untouched, so existing product specifications and phone
 * variants (native color/storage/region columns) are unaffected.
 */
class ProductAttributeValueObserver
{
    public function saving(ProductAttributeValue $value): void
    {
        if ($value->product_variant_id === null) {
            return;
        }

        $definition = AttributeDefinition::query()->find($value->attribute_definition_id);

        if ($definition === null || ! $definition->is_variant_defining) {
            return;
        }

        $variant = ProductVariant::query()
            ->with(['attributeValues.attributeDefinition', 'attributeValues.attributeOption'])
            ->find($value->product_variant_id);

        if ($variant === null) {
            return;
        }

        $pending = [$value];

        $errors = app(VariantSignatureService::class)->validate($variant, $pending, excludeValueId: $value->id);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
