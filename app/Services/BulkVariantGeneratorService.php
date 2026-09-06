<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cartesian bulk variant generator (PLAN #49).
 *
 * Takes per-attribute option selections (e.g. Size: [S,M,L], Color: [White,Black]),
 * expands them into every combination, and creates one ProductVariant per
 * combination with variant-scoped ProductAttributeValue rows linking it to the
 * chosen options. Combinations that already exist are skipped (never duplicated),
 * enforced through the same canonical signature VariantSignatureService computes.
 *
 * Strictly EAV-driven: the generator never writes to the deprecated native
 * phone columns (color/storage_gb/ram_gb/sim_type/region) — PLAN #48 keeps
 * those read-only legacy paths.
 */
final class BulkVariantGeneratorService
{
    /** Hard ceiling so an accidental 10×10×10 cannot thrash the database. */
    public const MAX_COMBINATIONS = 100;

    public function __construct(private readonly VariantSignatureService $signatures) {}

    public static function maxCombinations(): int
    {
        return (int) config('catalog.variant_max_combinations', self::MAX_COMBINATIONS);
    }

    /**
     * @param  array<int|string, mixed>  $selections  [definitionId => list<optionId>]
     * @param  int  $basePrice  minor-unit cents, applied to every generated variant
     * @param  string|null  $baseSku  SKU prefix; falls back to the product's model number
     * @return array{created: int, skipped: int}
     */
    public function generate(Product $product, array $selections, int $basePrice, ?string $baseSku = null): array
    {
        if ($basePrice <= 0) {
            throw ValidationException::withMessages([
                'base_price' => 'Base price must be greater than zero.',
            ]);
        }

        $sets = $this->resolveSets($selections);

        if ($sets === []) {
            throw ValidationException::withMessages([
                'attributes' => 'Select at least one option for each attribute.',
            ]);
        }

        $combinations = $this->cartesian($sets);

        $max = self::maxCombinations();

        if (count($combinations) > $max) {
            throw ValidationException::withMessages([
                'attributes' => sprintf(
                    '%d combinations exceed the %d-variant limit — reduce your selections.',
                    count($combinations),
                    $max,
                ),
            ]);
        }

        $prefix = $this->skuPrefix($product, $baseSku);
        $known = $this->existingSignatures($product);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($product, $combinations, $prefix, $basePrice, $known, &$created, &$skipped): void {
            foreach ($combinations as $options) {
                $signature = $this->signatureOf($options);

                if (in_array($signature, $known, true)) {
                    $skipped++;

                    continue;
                }

                try {
                    $variant = ProductVariant::query()->create([
                        'product_id' => $product->id,
                        'sku' => $this->uniqueSku($prefix, $options),
                        'price' => $basePrice,
                        'is_active' => true,
                    ]);

                    foreach ($options as $option) {
                        $variant->attributeValues()->create([
                            'product_id' => $product->id,
                            'attribute_definition_id' => $option->attribute_definition_id,
                            'attribute_option_id' => $option->id,
                        ]);
                    }

                    $known[] = $signature;
                    $created++;
                } catch (ValidationException) {
                    // Safety net: the ProductAttributeValueObserver rejected the
                    // combination even though our pre-check passed — treat as skip.
                    $skipped++;
                }
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Validate and resolve the selected options per attribute definition.
     * Only variant-defining choice-type (select/multiselect) definitions qualify;
     * options must belong to their definition and the current tenant (global scope).
     *
     * @param  array<int|string, mixed>  $selections
     * @return list<array{definition: AttributeDefinition, options: list<AttributeOption>}>
     */
    private function resolveSets(array $selections): array
    {
        $sets = [];

        foreach ($selections as $definitionId => $optionIds) {
            $raw = is_array($optionIds) ? $optionIds : [];
            $ids = array_values(array_map(intval(...), array_filter($raw, fn (mixed $id): bool => filled($id))));

            if ($ids === []) {
                continue;
            }

            $definition = AttributeDefinition::query()->find((int) $definitionId);

            if ($definition === null || ! $definition->is_variant_defining || ! $definition->isChoiceType()) {
                continue;
            }

            // T1 hardening: explicit tenant ownership check (defense in depth beyond global scope)
            if (tenant() !== null && (int) $definition->tenant_id !== (int) tenant()->id) {
                continue;
            }

            /** @var list<AttributeOption> $options */
            $options = AttributeOption::query()
                ->whereIn('id', $ids)
                ->where('attribute_definition_id', $definition->id)
                ->when(tenant() !== null, fn ($q) => $q->where('tenant_id', tenant()->id))
                ->orderBy('sort_order')
                ->get()
                ->all();

            if ($options !== []) {
                $sets[] = ['definition' => $definition, 'options' => $options];
            }
        }

        usort($sets, fn (array $a, array $b): int => $a['definition']->id <=> $b['definition']->id);

        return $sets;
    }

    /**
     * Iterative cartesian expansion preserving definition order.
     *
     * @param  list<array{definition: AttributeDefinition, options: list<AttributeOption>}>  $sets
     * @return list<list<AttributeOption>>
     */
    private function cartesian(array $sets): array
    {
        $result = [[]];

        foreach ($sets as $set) {
            $expanded = [];

            foreach ($result as $combination) {
                foreach ($set['options'] as $option) {
                    $expanded[] = [...$combination, $option];
                }
            }

            $result = $expanded;
        }

        return $result;
    }

    /**
     * Canonical combination signature — mirrors VariantSignatureService::mapByDefinition
     * exactly (definition id => option label, ksort, serialize) so generated combos
     * compare equal against persisted variant signatures.
     *
     * @param  list<AttributeOption>  $options
     */
    private function signatureOf(array $options): string
    {
        $map = [];

        foreach ($options as $option) {
            $map[$option->attribute_definition_id] = $option->label;
        }

        ksort($map);

        return serialize($map);
    }

    /**
     * Signatures of every existing EAV variant of the product; phone-native
     * variants have null signatures and are correctly excluded.
     *
     * @return list<string>
     */
    private function existingSignatures(Product $product): array
    {
        $signatures = [];

        $variants = $product->variants()
            ->with(['attributeValues.attributeDefinition', 'attributeValues.attributeOption'])
            ->get();

        foreach ($variants as $variant) {
            $signature = $this->signatures->signature($variant);

            if ($signature !== null) {
                $signatures[] = $signature;
            }
        }

        return $signatures;
    }

    /**
     * Collision-safe SKU: PREFIX-OPTION-OPTION, numeric suffix (-2, -3, …) when
     * the tenant already owns the candidate SKU.
     *
     * @param  list<AttributeOption>  $options
     */
    private function uniqueSku(string $prefix, array $options): string
    {
        $parts = array_map(
            fn (AttributeOption $option): string => Str::upper(Str::slug($option->label)) ?: 'OPT',
            $options,
        );

        $base = trim(Str::upper(Str::slug($prefix)).'-'.implode('-', $parts), '-');
        $base = Str::limit($base, 48, '');

        $candidate = $base;
        $attempt = 1;

        while (ProductVariant::query()->where('sku', $candidate)->exists()) {
            $attempt++;
            $candidate = $base.'-'.$attempt;
        }

        return $candidate;
    }

    private function skuPrefix(Product $product, ?string $baseSku): string
    {
        if (is_string($baseSku) && trim($baseSku) !== '') {
            return trim($baseSku);
        }

        if (is_string($product->model_number) && trim($product->model_number) !== '') {
            return trim($product->model_number);
        }

        return 'P-'.$product->id;
    }
}
