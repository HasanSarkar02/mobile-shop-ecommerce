<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'subdomain' => $this->faker->unique()->slug(2),
            'status' => 'active',
            'plan' => 'paid',
            'currency' => 'BDT',
        ];
    }
}