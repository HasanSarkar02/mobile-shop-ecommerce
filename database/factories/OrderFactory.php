<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-TEST-'.$this->faker->unique()->numerify('######'),
            'invoice_number' => 'INV-TEST-'.$this->faker->unique()->numerify('######'),
            'status' => 'pending',
            'order_source' => 'website',
            'sales_channel' => 'online_store',
            'currency_code' => 'BDT',
            'currency_rate' => 1,
            'subtotal' => 0,
            'shipping_cost' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'placed_at' => now(),
        ];
    }
}