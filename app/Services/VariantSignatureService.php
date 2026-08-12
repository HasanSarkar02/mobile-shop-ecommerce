<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Resolves the canonical "combination signature" of a variant from its
 * variant-scoped, variant-defining attribute values, and guards the two
 * integrity invariants the variant system must hold:
 *
 *  1. a variant may not carry two values for the same variant-defining
 *     attribute (e.g. Size = S AND Size = L on one variant), and
 *  2. two variants of the same product may not carry the same combination
 *     (e.g. Red + M may only exist once per product).
 *
 * A variant with no variant-defining attribute values has no signature and is
 * exempt from the combination-uniqueness rule — this is exactly how existing
 * phone variants (native color/storage/region columns) keep working unchanged.
 */
class VariantSignatureService
{
    /**
     * Compute the deterministic combination signature for a variant, optionally
     * including/replacing rows from $pending (used while a row is being saved
     * but not yet persisted).
     *
     * @param  array<int, ProductAttributeValue>  $pending
     */
    public function signature(ProductVariant $variant, array $pending = [], ?int $excludeValueId = null): ?string
    {
        $rows = $this->combinedRows($variant, $pending, $excludeValueId);

        $map = $this->mapByDefinition($rows);

        if ($map === []) {
            return null;
        }

        ksort($map);

        return serialize($map);
    }

    /**
     * Return the list of variant-defining definitions that appear more than
     * once on the variant (the "two sizes on one variant" violation).
     *
     * @return array<string> alias: definition codes
     */
    public function duplicateDefinitions(ProductVariant $variant, array $pending = [], ?int $excludeValueId = null): array
    {
        $rows = $this->combinedRows($variant, $pending, $excludeValueId);
        $map = $this->mapByDefinition($rows);

        return $rows
            ->filter(fn ($row) => $row->attributeDefinition?->is_variant_defining === true)
            ->groupBy(fn ($row) => $row->attribute_definition_id)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->keys()
            ->map(fn ($id) => $rows->firstWhere('attribute_definition_id', $id)->attributeDefinition->code)
            ->values()
            ->all();
    }

    /**
     * Returns a sibling variant (same product, different id) that would
     * represent the same combination, or null.
     */
    public function findConflictingVariant(ProductVariant $variant, array $pending = [], ?int $excludeValueId = null): ?ProductVariant
    {
        $ownSignature = $this->signature($variant, $pending, $excludeValueId);

        if ($ownSignature === null) {
            return null;
        }

        $siblings = $variant->product->variants()
            ->whereKeyNot($variant->id)
            ->with(['attributeValues.attributeDefinition', 'attributeValues.attributeOption'])
            ->get();

        foreach ($siblings as $sibling) {
            if ($this->signature($sibling) === $ownSignature) {
                return $sibling;
            }
        }

        return null;
    }

    /**
     * Validate a variant's combination integrity and return a validation error
     * map (empty when valid).
     *
     * @return array<string, array<string>>
     */
    public function validate(ProductVariant $variant, array $pending = [], ?int $excludeValueId = null): array
    {
        $errors = [];

        $involved = $this->combinedRows($variant, $pending, $excludeValueId)
            ->contains(fn ($row) => $row->attributeDefinition?->is_variant_defining === true);

        if (! $involved || $this->signature($variant, $pending, $excludeValueId) === null) {
            return $errors;
        }

        foreach ($this->duplicateDefinitions($variant, $pending, $excludeValueId) as $code) {
            $errors['product_variant_id'][] = "Variant has more than one value for the \"{$code}\" attribute.";
        }

        $conflict = $this->findConflictingVariant($variant, $pending, $excludeValueId);

        if ($conflict !== null) {
            $errors['product_variant_id'][] = "Another variant with SKU \"{$conflict->sku}\" already exists for this combination.";
        }

        return $errors;
    }

    /**
     * Merge the variant's persisted attribute-value rows with any pending row,
     * replacing by id when the pending row is an update of an existing row.
     *
     * @param  array<int, ProductAttributeValue>  $pending
     */
    private function combinedRows(ProductVariant $variant, array $pending, ?int $excludeValueId): Collection
    {
        $rows = collect($variant->attributeValues)
            ->reject(fn ($row) => $row->product_variant_id === null)
            ->reject(fn ($row) => $row->id !== null && $row->id === $excludeValueId);

        foreach ($pending as $pendingRow) {
            $index = $rows->search(fn ($row) => $row->id !== null && $row->id === $pendingRow->id);

            if ($index !== false) {
                $rows = $rows->replace([$index => $pendingRow]);
            } else {
                $rows->push($pendingRow);
            }
        }

        return $rows;
    }

    /**
     * Build definition-id => display-value map for variant-defining rows.
     *
     * @param  Collection<int, ProductAttributeValue>  $rows
     * @return array<int, string>
     */
    private function mapByDefinition(Collection $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $definition = $row->attributeDefinition;

            if ($definition === null || ! $definition->is_variant_defining) {
                continue;
            }

            $map[$definition->id] = $row->displayValue() ?? '';
        }

        return $map;
    }
}
