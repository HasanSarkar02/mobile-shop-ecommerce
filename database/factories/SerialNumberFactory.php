<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SerialNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class SerialNumberFactory extends Factory
{
    protected $model = SerialNumber::class;

    public function definition(): array
    {
        return [
            'imei_or_serial' => $this->faker->unique()->numerify('###############'),
            'status' => 'available',
        ];
    }
}
