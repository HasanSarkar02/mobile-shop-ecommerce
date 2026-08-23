<?php

declare(strict_types=1);

namespace App\Support;

final class ProductFilterState
{
    public function __construct(
        public readonly array $brandIds = [],
        public readonly ?int $priceMin = null,
        public readonly ?int $priceMax = null,
        public readonly bool $inStockOnly = false,
        public readonly bool $emiOnly = false,
        public readonly bool $warrantyOnly = false,
        public readonly bool $onSaleOnly = false,
        public readonly bool $newArrivalOnly = false,
        public readonly bool $officialOnly = false,
        public readonly array $collectionIds = [],
        /** @var array<string, array<string>> attribute code => selected values */
        public readonly array $attributes = [],
        public readonly string $sort = 'featured',
        public readonly int $page = 1,
        public readonly int $perPage = 24,
        public readonly ?string $searchTerm = null,
    ) {}

    public function isFiltered(): bool
    {
        return $this->brandIds !== []
            || $this->priceMin !== null
            || $this->priceMax !== null
            || $this->inStockOnly
            || $this->emiOnly
            || $this->warrantyOnly
            || $this->onSaleOnly
            || $this->newArrivalOnly
            || $this->officialOnly
            || $this->collectionIds !== []
            || $this->attributes !== []
            || $this->sort !== 'featured';
    }

    /**
     * Immutable page replacement — callers can never mutate a shared state
     * instance (readonly properties), they derive a new one.
     */
    public function withPage(int $page): self
    {
        return new self(
            brandIds: $this->brandIds,
            priceMin: $this->priceMin,
            priceMax: $this->priceMax,
            inStockOnly: $this->inStockOnly,
            emiOnly: $this->emiOnly,
            warrantyOnly: $this->warrantyOnly,
            onSaleOnly: $this->onSaleOnly,
            newArrivalOnly: $this->newArrivalOnly,
            officialOnly: $this->officialOnly,
            collectionIds: $this->collectionIds,
            attributes: $this->attributes,
            sort: $this->sort,
            page: max(1, $page),
            perPage: $this->perPage,
            searchTerm: $this->searchTerm,
        );
    }

    /**
     * Normalizes a raw price input into cents. Empty, non-numeric, and
     * non-positive values are ignored (null) rather than clamped, so a stray
     * "-5" or "abc" in the query string can never distort the range filter.
     */
    public static function priceFromInput(mixed $input): ?int
    {
        if ($input === null || (is_string($input) && trim($input) === '')) {
            return null;
        }

        if (! is_numeric($input)) {
            return null;
        }

        $value = (float) $input;

        if (! is_finite($value) || $value <= 0) {
            return null;
        }

        return (int) round($value * 100);
    }
}
