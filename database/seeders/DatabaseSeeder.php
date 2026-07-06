<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->create([
            'name' => 'Demo Store',
            'subdomain' => 'demo',
            'status' => 'active',
        ]);
    }
}