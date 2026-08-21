<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CourierProvider;
use App\Services\Shipping\PathaoDriver;
use App\Services\Shipping\SteadfastDriver;
use Illuminate\Database\Seeder;

class CourierProviderSeeder extends Seeder
{
    public function run(): void
    {
        CourierProvider::query()->updateOrCreate(['code' => 'steadfast'], [
            'name' => 'Steadfast Courier',
            'display_name' => 'Steadfast Courier',
            'base_url' => 'https://portal.packzy.com/api/v1',
            'base_url_sandbox' => 'https://portal.packzy.com/api/v1',
            'base_url_live' => 'https://portal.packzy.com/api/v1',
            'auth_type' => 'api_key',
            'required_fields' => ['api_key', 'secret_key'],
            'driver_class' => SteadfastDriver::class,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        CourierProvider::query()->updateOrCreate(['code' => 'pathao'], [
            'name' => 'Pathao Courier',
            'display_name' => 'Pathao Courier',
            'base_url' => 'https://courier-api.pathao.com',
            'base_url_sandbox' => 'https://courier-api-sandbox.pathao.com',
            'base_url_live' => 'https://courier-api.pathao.com',
            'auth_type' => 'oauth',
            'required_fields' => ['client_id', 'client_secret', 'username', 'password'],
            'driver_class' => PathaoDriver::class,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
