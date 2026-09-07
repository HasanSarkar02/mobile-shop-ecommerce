<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttributeDataType;
use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSpecificationBulkService
{
    /**
     * Get applicable definitions for a product.
     * If product has category and that category has mapped definitions, return only mapped.
     * Otherwise return all tenant definitions. Grouped and sorted.
     *
     * @return Collection<int, AttributeDefinition>
     */
    public function applicableDefinitions(Product $product, bool $showAll = false): Collection
    {
        $query = AttributeDefinition::query()
            ->with('options')
            ->orderBy('group_sort_order')
            ->orderBy('sort_order')
            ->orderBy('label');

        // Tenant is enforced via BelongsToTenant global scope

        if (! $showAll && $product->category_id) {
            $mappedIds = DB::table('category_attribute_definition')
                ->where('category_id', $product->category_id)
                ->pluck('attribute_definition_id')
                ->all();

            if ($mappedIds !== []) {
                $query->whereIn('id', $mappedIds);
            }
        }

        return $query->get();
    }

    /**
     * Group definitions by group name, sorted by group_sort_order.
     *
     * @return Collection<string, Collection<int, AttributeDefinition>>
     */
    public function groupedDefinitions(Collection $definitions): Collection
    {
        return $definitions->groupBy(fn (AttributeDefinition $d): string => $d->group ?: 'General')
            ->sortBy(fn (Collection $items): array => [$items->min(fn (AttributeDefinition $d): int => $d->group_sort_order ?? 0), $items->first()->group ?? 'General']);
    }

    /**
     * Load existing product-level values keyed by attribute_definition_id.
     *
     * @return Collection<int, ProductAttributeValue>
     */
    public function existingValues(Product $product): Collection
    {
        return ProductAttributeValue::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->get()
            ->keyBy('attribute_definition_id');
    }

    /**
     * Bulk upsert/delete for product-level specifications.
     *
     * @param  array<string, mixed>  $specs  ['specs' => [ definitionId => ['value_string'|'value_integer'|...| 'attribute_option_id' ] ]]
     */
    public function sync(Product $product, array $specs, bool $showAll = false): void
    {
        $tenantId = tenant()?->id ?? $product->tenant_id;

        if ((int) $product->tenant_id !== (int) $tenantId) {
            throw ValidationException::withMessages(['product' => 'Product does not belong to this store.']);
        }

        // Validate applicable check unless showAll
        $applicableIds = $this->applicableDefinitions($product, $showAll)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $applicableSet = array_flip($applicableIds);

        DB::transaction(function () use ($product, $specs, $applicableSet, $tenantId): void {
            // Lock product row for optimistic concurrency (simple)
            Product::query()->whereKey($product->getKey())->lockForUpdate()->first();

            $existingMap = $this->existingValues($product);

            foreach ($specs as $definitionIdRaw => $payload) {
                $definitionId = (int) $definitionIdRaw;

                // Tenant and applicability validation
                $definition = AttributeDefinition::query()->find($definitionId);
                if (! $definition) {
                    throw ValidationException::withMessages(["specs.{$definitionId}" => 'Invalid attribute.']);
                }
                if ((int) $definition->tenant_id !== (int) $tenantId) {
                    throw ValidationException::withMessages(["specs.{$definitionId}" => 'Attribute does not belong to this store.']);
                }
                if (! isset($applicableSet[$definitionId]) && ! $showAll) {
                    // If not in applicable set and not showAll, skip (defense: attacker could POST arbitrary id)
                    throw ValidationException::withMessages(["specs.{$definitionId}" => 'Attribute is not applicable to this product category. Enable Show all to use it.']);
                }

                // Check is_required if applicable and payload is empty
                $isEmpty = $this->isEmptyPayload($definition, $payload);
                $existing = $existingMap->get($definitionId);

                if ($isEmpty) {
                    if ($existing) {
                        $existing->delete();
                    }

                    continue;
                }

                // Validate option belongs to definition + tenant
                $columns = $this->mapPayloadToColumns($definition, $payload);

                // If no change, skip update to avoid updated_at churn
                if ($existing && $this->isSameValue($existing, $columns)) {
                    continue;
                }

                // Upsert: delete-on-empty already handled, now create/update
                // Use updateOrCreate with tenant/product/definition/variant(null) uniqueness via app check,
                // not DB unique due to NULL semantics in MySQL.
                $attributes = [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'attribute_definition_id' => $definitionId,
                ];

                // Ensure tenant_id is set via BelongsToTenant creating, but we set explicitly
                $value = ProductAttributeValue::query()->where($attributes)->first();

                if ($value) {
                    $value->update($columns);
                } else {
                    ProductAttributeValue::query()->create(array_merge($attributes, $columns));
                }
            }
        });
    }

    public function isEmptyPayload(AttributeDefinition $definition, mixed $payload): bool
    {
        if (! is_array($payload)) {
            return true;
        }

        return match ($definition->data_type) {
            AttributeDataType::Text => trim((string) ($payload['value_string'] ?? '')) === '',
            AttributeDataType::Number => ($payload['value_integer'] ?? null) === null || trim((string) $payload['value_integer']) === '',
            AttributeDataType::Decimal => ($payload['value_decimal'] ?? null) === null || trim((string) $payload['value_decimal']) === '',
            AttributeDataType::Boolean => false, // boolean toggle always has value (true/false), never empty -> never delete
            AttributeDataType::Select => empty($payload['attribute_option_id']),
            AttributeDataType::MultiSelect => empty($payload['attribute_option_id']),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mapPayloadToColumns(AttributeDefinition $definition, array $payload): array
    {
        return match ($definition->data_type) {
            AttributeDataType::Text => [
                'attribute_option_id' => null,
                'value_string' => trim((string) $payload['value_string']),
                'value_integer' => null,
                'value_decimal' => null,
                'value_boolean' => null,
            ],
            AttributeDataType::Number => [
                'attribute_option_id' => null,
                'value_string' => null,
                'value_integer' => (int) $payload['value_integer'],
                'value_decimal' => null,
                'value_boolean' => null,
            ],
            AttributeDataType::Decimal => [
                'attribute_option_id' => null,
                'value_string' => null,
                'value_integer' => null,
                'value_decimal' => (string) $payload['value_decimal'],
                'value_boolean' => null,
            ],
            AttributeDataType::Boolean => [
                'attribute_option_id' => null,
                'value_string' => null,
                'value_integer' => null,
                'value_decimal' => null,
                'value_boolean' => (bool) ($payload['value_boolean'] ?? false),
            ],
            AttributeDataType::Select => $this->resolveSingleOption($definition, $payload['attribute_option_id'] ?? null),
            AttributeDataType::MultiSelect => $this->resolveSingleOption($definition, $payload['attribute_option_id'] ?? null), // currently single, multi would need pivot
        };
    }

    private function resolveSingleOption(AttributeDefinition $definition, mixed $optionId): array
    {
        if (empty($optionId)) {
            throw ValidationException::withMessages(['attribute_option_id' => 'Option is required for '.$definition->label]);
        }

        $option = AttributeOption::query()
            ->where('id', $optionId)
            ->where('attribute_definition_id', $definition->id)
            ->first();

        if (! $option) {
            throw ValidationException::withMessages(['attribute_option_id' => 'Invalid option for '.$definition->label]);
        }

        if (tenant() && (int) $option->tenant_id !== (int) tenant()->id) {
            throw ValidationException::withMessages(['attribute_option_id' => 'Option does not belong to this store.']);
        }

        return [
            'attribute_option_id' => $option->id,
            'value_string' => null,
            'value_integer' => null,
            'value_decimal' => null,
            'value_boolean' => null,
        ];
    }

    private function isSameValue(ProductAttributeValue $existing, array $columns): bool
    {
        return (int) ($existing->attribute_option_id ?? 0) === (int) ($columns['attribute_option_id'] ?? 0)
            && (string) ($existing->value_string ?? '') === (string) ($columns['value_string'] ?? '')
            && (string) ($existing->value_integer ?? '') === (string) ($columns['value_integer'] ?? '')
            && (string) ($existing->value_decimal ?? '') === (string) ($columns['value_decimal'] ?? '')
            && (bool) ($existing->value_boolean ?? false) === (bool) ($columns['value_boolean'] ?? false);
    }
}
