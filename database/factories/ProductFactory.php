<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'model_number' => $this->faker->bothify('MDL-####'),
            'base_price' => $this->faker->numberBetween(1000000, 15000000),
            'currency' => 'BDT',
            'status' => ProductStatus::Draft,
            'is_featured' => false,
            'is_serialized' => true,
        ];
    }
}