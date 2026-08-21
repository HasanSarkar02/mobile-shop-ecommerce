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

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'display_name',
        'type',
        'provider',
        'account_number',
        'account_name',
        'bank_name',
        'branch_name',
        'instructions',
        'gateway_driver',
        'gateway_mode',
        'credentials',
        'fee_type',
        'fee_value',
        'min_order_amount',
        'max_order_amount',
        'requires_verification',
        'gateway_ownership',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_active' => 'boolean',
            'requires_verification' => 'boolean',
            'credentials' => 'encrypted:array',
            'fee_value' => 'integer',
            'min_order_amount' => 'integer',
            'max_order_amount' => 'integer',
        ];
    }

    public function displayName(): string
    {
        return $this->display_name ?: $this->name;
    }

    public function isCod(): bool
    {
        return $this->type instanceof PaymentMethodType && $this->type->isCod();
    }

    public function isManual(): bool
    {
        return $this->type instanceof PaymentMethodType && $this->type->isManual();
    }

    public function isOnline(): bool
    {
        return $this->type instanceof PaymentMethodType && $this->type->isOnline();
    }

    public function isShopOwned(): bool
    {
        return ($this->gateway_ownership ?? 'shop') === 'shop';
    }
}
