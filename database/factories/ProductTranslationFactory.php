<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductTranslationFactory extends Factory
{
    protected $model = ProductTranslation::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'locale' => 'en',
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 9999),
        ];
    }
}
