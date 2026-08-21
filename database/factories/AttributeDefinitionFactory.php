<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttributeDataType;
use App\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeDefinitionFactory extends Factory
{
    protected $model = AttributeDefinition::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(2),
            'label' => ucfirst($this->faker->words(2, true)),
            'data_type' => AttributeDataType::Text,
            'is_global' => true,
            'is_filterable' => true,
            'is_variant_defining' => false,
        ];
    }
}
