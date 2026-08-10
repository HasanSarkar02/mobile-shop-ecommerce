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
    ) {
    }

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
}