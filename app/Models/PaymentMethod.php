<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['name', 'type','gateway_driver', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_active' => 'boolean',
        ];
    }
}