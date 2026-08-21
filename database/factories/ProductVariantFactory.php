<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'inventory_type' => 'tracked',
            'fulfillment_strategy' => 'stock',
            'availability' => 'in_stock',
            'is_active' => true,
        ];
    }
}
