<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VariantAvailability;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->bothify('SKU-????-####')),
            'price' => $this->faker->numberBetween(1000000, 15000000),
            'currency' => 'BDT',
            'availability' => VariantAvailability::InStock,
            'is_active' => true,
        ];
    }
}